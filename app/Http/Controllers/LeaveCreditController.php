<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveCredit;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaveCreditController extends Controller
{
    public function index($employeeId)
    {
        // OPTIMIZED: INNER JOIN instead of two separate queries
        // Fixed: last_name → surname bug
        $employee = Employee::select(['id', 'first_name', 'surname'])
            ->findOrFail($employeeId);

        // OPTIMIZED: INNER JOIN for leaveConfiguration (always exists)
        $credits = LeaveCredit::select([
            'leave_credits.id',
            'leave_credits.total_credits',
            'leave_credits.used_credits',
            'leave_credits.remaining_balance',
            'leave_credits.year',
            'leave_configurations.name as leave_type',
            'leave_configurations.code',
        ])
            ->join('leave_configurations', 'leave_credits.leave_configuration_id', '=', 'leave_configurations.id')
            ->where('leave_credits.employee_id', $employeeId)
            ->where('leave_credits.year', now()->year) // uses year index!
            ->get();

        return response()->json([
            'employee' => $employee->first_name . ' ' . $employee->surname,
            'year'     => now()->year,
            'credits'  => $credits,
        ]);
    }

    public function initializeSingleEmployeeCredits($employeeId, $targetYear = null)
    {
        $targetYear = $targetYear ?? now()->year;
        $previousYear = $targetYear - 1;

        $employee = Employee::findOrFail($employeeId);
        $configs = LeaveConfiguration::all();
        $now = now()->toDateTimeString(); // FIXED: Safe string for raw batch inserts

        $creditsToInsert = [];

        // Filter configurations based on employee status and statutory rules
        $eligibleConfigs = $configs->filter(function ($config) use ($employee) {
            // 1. JO SPECIAL CASE: Only Wellness (WL) is allowed for Job Orders
            if ($employee->employment_status === 'job_order') {
                return $config->code === 'WL';
            }

            // Replace line 66 with this block:
            $appTo = $config->application_to;
            $allowedStatuses = is_array($appTo) ? $appTo : (json_decode($appTo, true) ?? []);

            return in_array('all', $allowedStatuses) || in_array($employee->employment_status, $allowedStatuses);
            // return in_array('all', $config->application_to) || in_array($employee->employment_status, $config->application_to);
        });

        // OPTIMIZED: Get existing credits for this single employee to prevent loop queries
        $existingCreditIds = LeaveCredit::where('employee_id', $employeeId)
            ->where('year', $targetYear)
            ->pluck('leave_configuration_id')
            ->toArray();

        foreach ($eligibleConfigs as $config) {
            // Check in-memory array instead of hitting DB inside the loop
            if (!in_array($config->id, $existingCreditIds)) {
                $startingCredits = 0;

                // Fixed Leaves (WL, FL, SPL)
                if ($config->credit_type === 'fixed') {
                    $startingCredits = $config->fixed_days ?? 0;

                    // SPECIAL OVERRIDE: If it's Wellness for a JO, force the 3 days
                    if ($config->code === 'WL' && $employee->employment_status === 'job_order') {
                        $startingCredits = 5;
                    }
                }
                // Monthly Accumulating Leaves (VL, SL) -> Carry over check
                else if ($config->can_carry_over) {
                    $previousRecord = LeaveCredit::where('employee_id', $employeeId)
                        ->where('leave_configuration_id', $config->id)
                        ->where('year', $previousYear)
                        ->first();

                    $startingCredits = $previousRecord ? $previousRecord->remaining_balance : 0;
                }

                $creditsToInsert[] = [
                    'employee_id'            => $employeeId,
                    'leave_configuration_id' => $config->id,
                    'year'                   => $targetYear,
                    'total_credits'          => $startingCredits,
                    'used_credits'           => 0,
                    'remaining_balance'      => $startingCredits,
                    'last_updated'           => $now,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }
        }

        if (!empty($creditsToInsert)) {
            LeaveCredit::insert($creditsToInsert);
        }

        return true;
    }

    public function initializeAllCredits(Request $request)
    {
        $targetYear = $request->input('year', now()->year);
        $previousYear = $targetYear - 1;

        $employees = Employee::where('is_active', true)->get();
        $configs = LeaveConfiguration::all();
        $now = now()->toDateTimeString(); // FIXED: Safe string for raw batch inserts

        // OPTIMIZED: Chunk fetch existing credits for the target year to check duplicates in-memory
        $existingCreditsMap = LeaveCredit::where('year', $targetYear)
            ->select('employee_id', 'leave_configuration_id')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                return $items->pluck('leave_configuration_id')->toArray();
            })
            ->toArray();

        // OPTIMIZED: Chunk fetch previous year balances to avoid loop queries during carry over checks
        $previousBalancesMap = LeaveCredit::where('year', $previousYear)
            ->select('employee_id', 'leave_configuration_id', 'remaining_balance')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                return $items->keyBy('leave_configuration_id')->map->remaining_balance->toArray();
            })
            ->toArray();

        $creditsToInsert = [];

        foreach ($employees as $employee) {

            // Inside initializeAllCredits() method
            $eligibleConfigs = $configs->filter(function ($config) use ($employee) {

                // 1. JO SPECIAL CASE: Only Wellness (WL) is allowed for Job Orders
                if ($employee->employment_status === 'job_order') {
                    return $config->code === 'WL';
                }

                // 2. FLEXIBLE STATUS CHECK:
                // Because of the 'array' cast in your Model, 
                // $config->application_to is already a PHP array.
                return in_array('all', $config->application_to) ||
                    in_array($employee->employment_status, $config->application_to);
            });

            // Get already initialized configuration IDs for this employee
            $employeeExistingConfigs = $existingCreditsMap[$employee->id] ?? [];

            foreach ($eligibleConfigs as $config) {
                // Check in-memory instead of executing: LeaveCredit::where(...)->exists()
                if (!in_array($config->id, $employeeExistingConfigs)) {
                    $startingCredits = 0;

                    if ($config->credit_type === 'fixed') {
                        $startingCredits = $config->fixed_days ?? 0;
                        // SPECIAL OVERRIDE: If it's Wellness for a JO, force the 3 days
                        if ($config->code === 'WL' && $employee->employment_status === 'job_order') {
                            $startingCredits = 3;
                        }
                    } else if ($config->can_carry_over) {
                        // Retrieve carry over balance in-memory
                        $startingCredits = $previousBalancesMap[$employee->id][$config->id] ?? 0;
                    }

                    $creditsToInsert[] = [
                        'employee_id'            => $employee->id,
                        'leave_configuration_id' => $config->id,
                        'year'                   => $targetYear,
                        'total_credits'          => $startingCredits,
                        'used_credits'           => 0,
                        'remaining_balance'      => $startingCredits,
                        'last_updated'           => $now,
                        'created_at'             => $now,
                        'updated_at'             => $now,
                    ];
                }
            }
        }

        // Batch insert the new records in chunks of 500 for optimal database batch writes
        if (!empty($creditsToInsert)) {
            foreach (array_chunk($creditsToInsert, 500) as $chunk) {
                LeaveCredit::insert($chunk);
            }
        }

        return response()->json([
            'message' => "Successfully initialized all leave credits for the year {$targetYear}!",
        ]);
    }

    public function update(Request $request, $employeeId, $creditId)
    {
        $validated = $request->validate([
            'total_credits'     => 'sometimes|numeric|min:0',
            'used_credits'      => 'sometimes|numeric|min:0',
            'remaining_balance' => 'sometimes|numeric|min:0',
        ]);

        $credit = LeaveCredit::where('employee_id', $employeeId)
            ->findOrFail($creditId);

        // OPTIMIZED & FIXED: Auto-calculate remaining balance logically if total or used change
        $total = $validated['total_credits'] ?? $credit->total_credits;
        $used = $validated['used_credits'] ?? $credit->used_credits;

        $validated['remaining_balance'] = max(0, $total - $used);
        $validated['last_updated'] = now();

        $credit->update($validated);

        return response()->json([
            'message' => 'Leave credit updated successfully',
            'credit'  => $credit->only([
                'id',
                'total_credits',
                'used_credits',
                'remaining_balance',
                'year',
                'last_updated'
            ]),
        ]);
    }

    //Mobile
    public function getLeaveCreditBalances(Request $request)
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $balances = LeaveCredit::join('leave_configurations', 'leave_credits.leave_configuration_id', '=', 'leave_configurations.id')
            ->where('leave_credits.employee_id', $employee->id)
            ->where('leave_credits.year', now()->year)
            ->select(
                'leave_configurations.id as leave_configuration_id',
                'leave_configurations.name',
                'leave_configurations.code',
                'leave_credits.remaining_balance'
            )
            ->get();

        return response()->json($balances);
    }
}
