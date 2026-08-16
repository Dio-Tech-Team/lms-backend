<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\EmploymentHistory;
use App\Models\Employee;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\LeaveCreditController;

class EmploymentHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($employeeId)
    {
        $employee = Employee::select(['id', 'first_name', 'surname'])
            ->findOrFail($employeeId);

        $promotion = EmploymentHistory::select('employee_id', 'previous_position', 'new_position', 'previous_employment_status', 'new_employment_status', 'effective_date', 'remarks')
            ->where('employee_id', $employeeId)
            ->orderBy('effective_date', 'desc') // Synced column name
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'employee' => $employee->first_name . ' ' . $employee->surname, // Fixed to use surname
            'promotion' => $promotion
        ]);
    }
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        // Validate all fields present in your updated migration
        $request->validate([
            'previous_position'          => 'nullable|string|max:255',
            // 'new_position'               => 'required|string|max:255',
            'new_position' => 'required|string|max:255|exists:positions,title',
            'previous_employment_status' => 'nullable|in:permanent,casual,elected,job_order,resigned,retired',
            'new_employment_status'      => 'required|in:permanent,casual,elected,job_order,resigned,retired',
            'effective_date'             => 'required|date',
            'remarks'                    => 'nullable|string',
        ]);

        $promotion = DB::transaction(function () use ($request, $employeeId, $employee) {
            // Single query: find and update employee position/status
            Employee::where('id', $employeeId)->update([
                'position'          => $request->new_position,
                'employment_status' => $request->new_employment_status,
            ]);
            $leaveController = new LeaveCreditController();
            $leaveController->initializeSingleEmployeeCredits($employeeId);
            // Create the history record

            $history = EmploymentHistory::create([
                'employee_id'                => $employeeId,
                'previous_position'          => $request->previous_position,
                'new_position'               => $request->new_position,
                'previous_employment_status' => $request->previous_employment_status,
                'new_employment_status'      => $request->new_employment_status,
                'effective_date'             => $request->effective_date,
                'remarks'                    => $request->remarks,
            ]);
            // NEW — log the change
            ActivityLog::create([
                'user_id'      => request()->user()->id,
                'action'       => 'employment_history.recorded',
                'description'  => "Recorded position change for {$employee->first_name} {$employee->surname}: {$request->new_position}",
                'subject_type' => 'Employee',
                'subject_id'   => $employeeId,
            ]);

            return $history;
        });


        return response()->json([
            'message'  => 'Employment history recorded successfully',
            'history'  => $promotion->only([
                'id',
                'employee_id',
                'previous_position',
                'new_position',
                'previous_employment_status',
                'new_employment_status',
                'effective_date',
                'remarks'
            ])
        ], 201);
    }

    public function destroy($employeeId, $promotionId)
    {
        // OPTIMIZED: single query instead of firstOrFail + delete
        $affected = EmploymentHistory::where('employee_id', $employeeId)
            ->where('id', $promotionId)
            ->delete();

        if (!$affected) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        // NEW — log the deletion
        ActivityLog::create([
            'user_id'      => request()->user()->id,
            'action'       => 'employment_history.deleted',
            'description'  => "Deleted employment history record #{$promotionId} for employee #{$employeeId}",
            'subject_type' => 'Employee',
            'subject_id'   => $employeeId,
        ]);
        return response()->json(['message' => 'Employment history deleted successfully']);
    }
}
