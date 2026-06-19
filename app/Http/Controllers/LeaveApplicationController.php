<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveApplication;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use App\Models\LeaveRecord;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

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
                    // 'start_date'  => $app->start_date,
                    // 'end_date'    => $app->end_date,
                    'start_date'  => Carbon::parse($app->start_date)->format('Y-m-d'),
                    'end_date'    => Carbon::parse($app->end_date)->format('Y-m-d'),
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
            ], 402);
        }

        // Get the configuration rules for this specific leave type
        $config = LeaveConfiguration::findOrFail($validated['leave_config_id']);

        if ($config->code === 'WL' && $validated['days_applied'] > 3) {
            return response()->json([
                'message' => 'Wellness leave cannot  exceed 3 consecutive days per application.'
            ], 402);
        }

        // Fetch the employee's current credit record for this year
        // $credit = LeaveCredit::where('employee_id', $employee->id)
        //     ->where('leave_config_id', $config->id)
        //     ->where('year', now()->year)
        //     ->first();

        // Inside LeaveApplicationController.php - Line 86
        $credit = LeaveCredit::where('employee_id', $request->employee_id)
            ->where('leave_configuration_id', $request->leave_type_id) // <-- Fix this key here!
            ->where('year', 2026)
            ->first();

        // if (!$credit || $credit->remaining_balance < $validated['days_applied']) {
        //     return response()->json([
        //         'message' => 'Insufficient leave balance. You only have ' . ($credit->remaining_balance ?? 0) . ' days remaining.'
        //     ], 402);
        // }

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
        // Fetch the credit details again to make sure things haven't changed since filing
        $credit = LeaveCredit::where('employee_id', $application->employee_id)
            ->where('leave_config_id', $application->leave_config_id)
            ->where('year', now()->year)
            ->first();

        // Double check balance right before committing deduction
        // if (!$credit || $credit->remaining_balance < $application->days_applied) {
        //     return response()->json([
        //         'message' => 'Cannot approve. Employee has insufficient leave balance.',
        //     ], 402);
        // }
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
            'remarks'         => $application->id,
        ]);

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

    public function generatePdf($id)
    {
        $application = LeaveApplication::with(['employee.department', 'leaveConfiguration', 'reviewedBy'])
            ->findOrFail($id);

        $code = $application->leaveConfiguration->code;

        // Get current leave credits for VL and SL
        $vlCredit = LeaveCredit::where('employee_id', $application->employee_id)
            ->whereHas('leaveConfiguration', fn($q) => $q->where('code', 'VL'))
            ->where('year', now()->year)
            ->first();

        $slCredit = LeaveCredit::where('employee_id', $application->employee_id)
            ->whereHas('leaveConfiguration', fn($q) => $q->where('code', 'SL'))
            ->where('year', now()->year)
            ->first();

        $data = [
            'application' => $application,
            'code'        => $code,
            'vl_total'    => $vlCredit->total_credits ?? 0,
            'vl_balance'  => $vlCredit->remaining_balance ?? 0,
            'sl_total'    => $slCredit->total_credits ?? 0,
            'sl_balance'  => $slCredit->remaining_balance ?? 0,
        ];

        $pdf = Pdf::loadView('pdf.leave-application', $data);

        return $pdf->stream('leave-application-' . $application->id . '.pdf');
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
