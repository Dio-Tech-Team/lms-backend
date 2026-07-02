<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveCredit;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use Illuminate\Support\Facades\DB;

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
            'employee' => $employee->first_name . ' ' . $employee->surname, // Fixed!
            'year'     => now()->year,
            'credits'  => $credits,
        ]);
    }

    public function initializeCredits($employeeId)
    {
        $employee = Employee::select(['id', 'employment_status'])
            ->findOrFail($employeeId);

        // Store intermediate result - fetch configs once
        $configurations = LeaveConfiguration::select(['id', 'application_to'])
            ->where(function ($query) use ($employee) {
                $query->where('application_to', 'all')
                    ->orWhere('application_to', $employee->employment_status);
            })
            ->get();

        // OPTIMIZED: batch insert instead of N+1 loop
        $now     = now();
        $year    = $now->year;
        $inserts = [];

        foreach ($configurations as $config) {
            // Check if already exists to avoid duplicates
            $exists = LeaveCredit::where('employee_id', $employeeId)
                ->where('leave_configuration_id', $config->id)
                ->where('year', $year)
                ->exists();

            if (!$exists) {
                $inserts[] = [
                    'employee_id'            => $employeeId,
                    'leave_configuration_id' => $config->id,
                    'year'                   => $year,
                    'total_credits'          => 0,
                    'used_credits'           => 0,
                    'remaining_balance'      => 0,
                    'last_updated'           => $now,
                    'created_at'             => $now,
                    'updated_at'             => $now,
                ];
            }
        }

        if (!empty($inserts)) {
            LeaveCredit::insert($inserts); // single batch insert!
        }

        return response()->json([
            'message' => 'Leave credits initialized successfully',
        ]);
    }

    public function update(Request $request, $employeeId, $creditId)
    {
        $validated = $request->validate([
            'total_credits'     => 'sometimes|numeric|min:0',
            'used_credits'      => 'sometimes|numeric|min:0',
            'remaining_balance' => 'sometimes|numeric|min:0',
        ]);

        // OPTIMIZED: single update query with last_updated included
        $validated['last_updated'] = now();

        $credit = LeaveCredit::where('employee_id', $employeeId)
            ->findOrFail($creditId);

        $credit->update($validated); // single write instead of two!

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
}
