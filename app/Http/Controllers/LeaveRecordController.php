<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveRecord;
use App\Models\Employee;
use App\Models\LeaveCredit;

class LeaveRecordController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    //Get all leave records
    public function index(Request $request)
    {
        $records = LeaveRecord::with(['employee', 'leaveConfiguration', 'recordedBy'])
            ->when($request->employee_id, function ($query) use ($request) {
                $query->where('employee_id', $request->employee_id);
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($record) {
                return [
                    'id'         => $record->id,
                    'employee'   => $record->employee->first_name . ' ' . $record->employee->last_name,
                    'leave_type' => $record->leaveConfiguration->name,
                    'code'       => $record->leaveConfiguration->code,
                    'start_date' => $record->start_date,
                    'end_date'   => $record->end_date,
                    'days_taken' => $record->days_taken,
                    'remarks'    => $record->remarks,
                    'recorded_by' => $record->recordedBy->username,
                    'created_at' => $record->created_at,
                ];
            });

        return response()->json($records);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_config_id' => 'required|exists:leave_configurations,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'days_taken' => 'required|numeric|min:0.5',
            'remarks' => 'nullable|string',
        ]);

        $validated['recorded_by'] = $request->user()->id;

        $record = LeaveRecord::create($validated);

        $credit = LeaveCredit::where('employee_id', $validated['employee_id'])
            ->where('leave_configuration_id', $validated['leave_config_id'])
            ->where('year', now()->year)
            ->first();

        if ($credit) {
            $credit->used_credits += $validated['days_taken'];
            $credit->remaining_balance -= $validated['days_taken'];
            $credit->last_updated = now();
            $credit->save();
        }

        return response()->json([
            'message' => 'Leave record created successfully',
            'record'  => $record,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $record = LeaveRecord::with(['employee', 'leaveConfiguration', 'recordedBy'])
            ->findOrFail($id);

        return response()->json($record);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $record = LeaveRecord::findOrFail($id);

        $validated = $request->validate([
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date',
            'days_taken' => 'sometimes|numeric|min:0.5',
            'remarks'    => 'nullable|string',
        ]);

        $record->update($validated);

        return response()->json([
            'message' => 'Leave record updated successfully',
            'record'  => $record,
        ]);
    }

    // Delete leave record
    public function destroy(string $id)
    {
        $record = LeaveRecord::findOrFail($id);

        // Restore leave credits
        $credit = LeaveCredit::where('employee_id', $record->employee_id)
            ->where('leave_config_id', $record->leave_config_id)
            ->where('year', now()->year)
            ->first();

        if ($credit) {
            $credit->used_credits -= $record->days_taken;
            $credit->remaining_balance += $record->days_taken;
            $credit->last_updated = now();
            $credit->save();
        }

        $record->delete();

        return response()->json([
            'message' => 'Leave record deleted successfully',
        ]);
    }
}
