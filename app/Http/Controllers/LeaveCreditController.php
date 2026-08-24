<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveCredit;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveRecord;
use App\Models\ActivityLog;
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

        // Attach FL usage-this-year, since FL's own credit row is just a 0/0/0 placeholder
        $flCredit = $credits->firstWhere('code', 'FL');
        if ($flCredit) {
            $flConfig = LeaveConfiguration::where('code', 'FL')->first();
            $flDaysTaken = $flConfig
                ? LeaveRecord::where('employee_id', $employeeId)
                ->where('leave_configuration_id', $flConfig->id)
                ->whereYear('start_date', now()->year)
                ->sum('days_taken')
                : 0;

            $flCredit->fl_days_taken = (float) $flDaysTaken;
            $flCredit->fl_days_cap = 5;
        }

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
        // $configs = LeaveConfiguration::all();
        // To this:
        $configs = LeaveConfiguration::where('is_active', true)->get();
        $now = now()->toDateTimeString(); // FIXED: Safe string for raw batch inserts

        $creditsToInsert = [];

        // Filter configurations based on employee status and statutory rules
        $eligibleConfigs = $configs->filter(function ($config) use ($employee) {
            // 1. JO SPECIAL CASE: Only Wellness (WL) is allowed for Job Orders
            if ($employee->employment_status === 'job_order') {
                return $config->code === 'WL';
            }
            // 2. Skip event-triggered leave types — these are granted manually by HR, not auto-initialized
            if ($config->grant_type === 'event_manual') {
                return false;
            }
            // // 3. SEX-BASED ELIGIBILITY GUARD
            // $femaleOnly = ['ML', 'VAWC', 'SLB', 'STL']; // Adjust codes as per your DB
            // $maleOnly   = ['PTL'];

            // $sex = strtolower($employee->sex ?? '');

            // if (in_array($config->code, $femaleOnly) && $sex !== 'female') {
            //     return false;
            // }
            // if (in_array($config->code, $maleOnly) && $sex !== 'male') {
            //     return false;
            // }

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
                    if ($config->code === 'VL') {
                        $employedFullPreviousYear = Carbon::parse($employee->date_hired)
                            ->lte(Carbon::create($previousYear, 1, 1));
                        if ($employedFullPreviousYear) {
                            $flConfig = LeaveConfiguration::where('code', 'FL')->first();
                            if ($flConfig) {
                                $flDaysTaken = \App\Models\LeaveRecord::where('employee_id', $employeeId)
                                    ->where('leave_configuration_id', $flConfig->id)
                                    ->whereYear('start_date', $previousYear)
                                    ->sum('days_taken');
                                $flShortfall = max(0, 5 - $flDaysTaken);
                                $startingCredits = max(0, $startingCredits - $flShortfall);
                            }
                        }
                    }
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
        // $configs = LeaveConfiguration::all();
        // To this:
        $configs = LeaveConfiguration::where('is_active', true)->get();
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

        $flConfig = LeaveConfiguration::where('code', 'FL')->first();
        foreach ($employees as $employee) {

            // Inside initializeAllCredits() method
            $eligibleConfigs = $configs->filter(function ($config) use ($employee) {

                // 1. JO SPECIAL CASE: Only Wellness (WL) is allowed for Job Orders
                if ($employee->employment_status === 'job_order') {
                    return $config->code === 'WL';
                }
                // 2. Skip event-triggered leave types — these are granted manually by HR, not auto-initialized
                if ($config->grant_type === 'event_manual') {
                    return false;
                }
                // // 3. SEX-BASED ELIGIBILITY GUARD
                // $femaleOnly = ['ML', 'VAWC', 'SLB', 'STL']; // Adjust codes as per your DB
                // $maleOnly   = ['PTL'];

                // $sex = strtolower($employee->sex ?? '');

                // if (in_array($config->code, $femaleOnly) && $sex !== 'female') {
                //     return false;
                // }
                // if (in_array($config->code, $maleOnly) && $sex !== 'male') {
                //     return false;
                // }

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
                        // SPECIAL OVERRIDE: If it's Wellness for a JO, force the 5 days
                        if ($config->code === 'WL' && $employee->employment_status === 'job_order') {
                            $startingCredits = 5;
                        }
                    } else if ($config->can_carry_over) {
                        // Retrieve carry over balance in-memory
                        $startingCredits = $previousBalancesMap[$employee->id][$config->id] ?? 0;
                        if ($config->code === 'VL') {
                            $employedFullPreviousYear = Carbon::parse($employee->date_hired)
                                ->lte(Carbon::create($previousYear, 1, 1));
                            if ($employedFullPreviousYear) {
                                if ($flConfig) {
                                    $flDaysTaken = \App\Models\LeaveRecord::where('employee_id', $employee->id)
                                        ->where('leave_configuration_id', $flConfig->id)
                                        ->whereYear('start_date', $previousYear)
                                        ->sum('days_taken');
                                    $flShortfall = max(0, 5 - $flDaysTaken);
                                    $startingCredits = max(0, $startingCredits - $flShortfall);
                                }
                            }
                        }
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
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'leave_credits.initialized_all',
            'description'  => "Initialized leave credits for all active employees — year {$targetYear} (" . count($creditsToInsert) . " records created)",
            'subject_type' => 'LeaveCredit',
            'subject_id'   => null,
        ]);

        return response()->json([
            'message' => "Successfully initialized all leave credits for the year {$targetYear}!",
        ]);
    }
    public function grantLeave(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'employee_id'             => 'required|exists:employees,id',
            'leave_configuration_id'  => 'required|exists:leave_configurations,id',
            'year'                    => 'required|integer|min:2020|max:2099',
            'days'                    => 'nullable|numeric|min:0.5',
            'remarks'                 => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        $config = LeaveConfiguration::findOrFail($validated['leave_configuration_id']);

        if ($config->grant_type !== 'event_manual') {
            return response()->json([
                'message' => "{$config->name} is auto-initialized annually and cannot be granted manually."
            ], 422);
        }

        if (!$config->is_active) {
            return response()->json([
                'message' => 'This leave type is no longer active.'
            ], 422);
        }

        // Sex-based eligibility — mirrors LeaveApplicationController::validateLeaveEligibility()
        $femaleOnly = ['ML', 'VAWC', 'SLB'];
        $maleOnly   = ['PTL'];

        if (in_array($config->code, $femaleOnly) && $employee->sex !== 'female') {
            return response()->json([
                'message' => "{$config->name} is only available for female employees."
            ], 422);
        }
        if (in_array($config->code, $maleOnly) && $employee->sex !== 'male') {
            return response()->json([
                'message' => "{$config->name} is only available for male employees."
            ], 422);
        }

        $days = $validated['days'] ?? $config->fixed_days;

        if ($days === null) {
            return response()->json([
                'message' => "{$config->name} has no default day amount configured — please specify 'days' explicitly."
            ], 422);
        }

        $existingGrant = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $config->id)
            ->where('year', $validated['year'])
            ->first();

        if ($existingGrant) {
            return response()->json([
                'message'        => "{$employee->first_name} {$employee->surname} already has a {$config->name} grant for {$validated['year']} ({$existingGrant->remaining_balance} day(s) remaining). Use the Edit Balance option if this needs to change.",
                'existing_grant' => $existingGrant,
            ], 409);
        }

        $credit = DB::transaction(function () use ($employee, $config, $validated, $days, $user) {
            $credit = LeaveCredit::create([
                'employee_id'            => $employee->id,
                'leave_configuration_id' => $config->id,
                'year'                   => $validated['year'],
                'total_credits'          => $days,
                'used_credits'           => 0,
                'remaining_balance'      => $days,
                'last_updated'           => now(),
            ]);

            ActivityLog::create([
                'user_id'      => $user->id,
                'action'       => 'leave_credit.granted',
                'description'  => "Granted {$days} day(s) of {$config->name} to {$employee->first_name} {$employee->surname} for {$validated['year']}" . (($validated['remarks'] ?? '') ? " — {$validated['remarks']}" : ''),
                'subject_type' => 'LeaveCredit',
                'subject_id'   => $credit->id,
            ]);

            return $credit;
        });

        return response()->json([
            'message' => "{$config->name} granted successfully",
            'data'    => $credit,
        ], 201);
    }
    // LeaveCreditController.php
    public function initializeCredits(Request $request, $employeeId)
    {
        $this->initializeSingleEmployeeCredits($employeeId, $request->input('year', now()->year));

        return response()->json(['message' => 'Leave credits initialized successfully']);
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

        // First-ever manual credit set on an untouched row = treat as the opening balance
        if (isset($validated['total_credits']) && (float) $credit->used_credits === 0.0 && (float) $credit->opening_balance === 0.0) {
            $validated['opening_balance'] = $validated['total_credits'];
        }
        $total = $validated['total_credits'] ?? $credit->total_credits;
        $used = $validated['used_credits'] ?? $credit->used_credits;

        $validated['remaining_balance'] = max(0, $total - $used);
        $validated['last_updated'] = now();

        $credit->update($validated);
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'leave_credit.updated',
            'description'  => "Manually adjusted leave credit #{$creditId} for employee #{$employeeId}",
            'subject_type' => 'LeaveCredit',
            'subject_id'   => $credit->id,
        ]);

        return response()->json([
            'message' => 'Leave credit updated successfully',
            'credit'  => $credit->only([
                'id',
                'total_credits',
                'used_credits',
                'remaining_balance',
                'opening_balance',
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
            ->where('leave_configurations.is_active', true)
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
