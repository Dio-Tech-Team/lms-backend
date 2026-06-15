<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveApplication;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use App\Models\LeaveRecord;

class LeaveApplicationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $applications = LeaveApplication::with(['employee', 'leaveConfiguration', 'reviewedBy'])
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($app) {
                return [
                    'id'          => $app->id,
                    'employee'    => $app->employee->first_name . ' ' . $app->employee->last_name,
                    'employee_id' => $app->employee_id,
                    'leave_type'  => $app->leaveConfiguration->name,
                    'code'        => $app->leaveConfiguration->code,
                    'start_date'  => $app->start_date,
                    'end_date'    => $app->end_date,
                    'days_applied' => $app->days_applied,
                    'reason' => $app->reason,
                    'status'      => $app->status,
                    'applied_at'  => $app->applied_at,
                    'reviewed_by' => $app->reviewedBy?->username,
                    'reviewed_at' => $app->reviewed_at,
                ];
            });

        return response()->json($applications);
    }

    /**
     * Store a newly created resource in storage.
     */
    // Employee files a leave application (from mobile)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_config_id' => 'required|exists:leave_configurations,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'days_applied'    => 'required|numeric|min:0.5',
            'reason' => 'nullable|string',
        ]);

        $employee = $request->user()->employee;

        // 2. ADDED SAFETY CHECK HERE: Stops the crash if employee profile is missing
        if (!$employee) {
            return response()->json([
                'message' => 'The authenticated user is not linked to an employee profile.'
            ], 422);
        }

        $application = LeaveApplication::create([
            'employee_id'     => $employee->id,
            'leave_config_id' => $validated['leave_config_id'],
            'start_date'      => $validated['start_date'],
            'end_date'        => $validated['end_date'],
            'days_applied'    => $validated['days_applied'],
            'reason'          =>  $validated['reason'], // ← add this
            'status'          => 'pending',
            'applied_at'      => now(),
        ]);
        return response()->json([
            'message'     => 'Leave application submitted successfully',
            'data' => $application,
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is already ' . $application->status,
            ], 400);
        }

        // Update application status
        $application->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        LeaveRecord::create([
            'employee_id'     => $application->employee_id,
            'leave_config_id' => $application->leave_config_id,
            'recorded_by'     => $request->user()->id,
            'start_date'      => $application->start_date,
            'end_date'        => $application->end_date,
            'days_taken'      => $application->days_applied,
            'remarks'         => 'Approved leave application #' . $application->id,
        ]);

        $credit = LeaveCredit::where('employee_id', $application->employee_id)
            ->where('leave_config_id', $application->leave_config_id)
            ->where('year', now()->year)
            ->first();


        if ($credit) {
            $credit->used_credits += $application->days_applied;
            $credit->remaining_balance -= $application->days_applied;
            $credit->last_updated = now();
            $credit->save();
        }

        return response()->json([
            'message' => 'Leave application approved successfully',
        ]);
    }

    public function cancel(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is already ' . $application->status,
            ], 400);
        }

        $application->update([
            'status'      => 'cancelled',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Leave application cancelled successfully',
        ]);
    }
    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $application = LeaveApplication::with(['employee', 'leaveConfiguration', 'reviewedBy'])
            ->findOrFail($id);

        return response()->json($application);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
