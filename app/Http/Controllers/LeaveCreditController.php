<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveCredit;
use App\Models\Employee;
use App\Models\LeaveConfiguration;

class LeaveCreditController extends Controller
{
    // Get all leave credits for a specific employee
    public function index($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $credits = LeaveCredit::where('employee_id', $employeeId)
            ->where('year', now()->year)
            ->with('leaveConfiguration')
            ->get()
            ->map(function ($credit) {
                return [
                    'id'                => $credit->id,
                    'leave_type'        => $credit->leaveConfiguration->name,
                    'code'              => $credit->leaveConfiguration->code,
                    'total_credits'     => $credit->total_credits,
                    'used_credits'      => $credit->used_credits,
                    'remaining_balance' => $credit->remaining_balance,
                    'year'              => $credit->year,
                ];
            });

        return response()->json([
            'employee' => $employee->first_name . ' ' . $employee->last_name,
            'year'     => now()->year,
            'credits'  => $credits,
        ]);
    }

    // Initialize leave credits for a new employee
    public function initializeCredits($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $configurations = LeaveConfiguration::where('application_to', 'all')
            ->orWhere('application_to', $employee->employment_status)
            ->get();

        foreach ($configurations as $config) {
            LeaveCredit::firstOrCreate(
                [
                    'employee_id'     => $employeeId,
                    'leave_configuration_id' => $config->id,
                    'year'            => now()->year,
                ],
                [
                    'total_credits'     => 0,
                    'used_credits'      => 0,
                    'remaining_balance' => 0,
                    'last_updated'      => now(),
                ]
            );
        }

        return response()->json([
            'message' => 'Leave credits initialized successfully',
        ]);
    }

    // Update leave credit balance
    public function update(Request $request, $employeeId, $creditId)
    {
        $credit = LeaveCredit::where('employee_id', $employeeId)
            ->findOrFail($creditId);

        $validated = $request->validate([
            'total_credits'     => 'sometimes|numeric|min:0',
            'used_credits'      => 'sometimes|numeric|min:0',
            'remaining_balance' => 'sometimes|numeric|min:0',
        ]);

        $credit->update($validated);
        $credit->last_updated = now();
        $credit->save();

        return response()->json([
            'message' => 'Leave credit updated successfully',
            'credit'  => $credit,
        ]);
    }
}
