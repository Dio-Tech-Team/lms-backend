<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PromotionHistory;
use App\Models\Employee;

class PromotionHistoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $promotions = PromotionHistory::where('employee_id', $employeeId)
            ->orderBy('effective_date', 'desc') // Synced column name
            ->get();

        return response()->json([
            'employee' => $employee->first_name . ' ' . $employee->surname, // Fixed to use surname
            'promotions' => $promotions
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
            'new_position'               => 'required|string|max:255',
            'previous_employment_status' => 'nullable|in:permanent,casual,elected,job_order',
            'new_employment_status'      => 'required|in:permanent,casual,elected,job_order',
            'effective_date'             => 'required|date',
            'remarks'                    => 'nullable|string',
        ]);

        // Automatically update the Employee's current profile status
        $employee->update([
            'position'          => $request->new_position,
            'employment_status' => $request->new_employment_status
        ]);

        // Create the history tracking record
        $promotion = PromotionHistory::create([
            'employee_id'                => $employeeId,
            'previous_position'          => $request->previous_position,
            'new_position'               => $request->new_position,
            'previous_employment_status' => $request->previous_employment_status,
            'new_employment_status'      => $request->new_employment_status,
            'effective_date'             => $request->effective_date, // Synced column name
            'remarks'                    => $request->remarks,
        ]);

        return response()->json([
            'message' => 'Promotion history created successfully',
            'promotion' => $promotion
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    // public function show(string $id) {}

    // /**
    //  * Update the specified resource in storage.
    //  */
    // public function update(Request $request, string $id)
    // {
    //     //
    // }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($employeeId, $promotionId)
    {
        $promotion = PromotionHistory::where('employee_id', $employeeId)
            ->where('id', $promotionId)
            ->firstOrFail();

        $promotion->delete();

        return response()->json([
            'message' => 'Promotion history deleted successfully'
        ]);
    }
}
