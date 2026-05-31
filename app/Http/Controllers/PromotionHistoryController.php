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
            ->orderBy('promotion_date', 'desc')
            ->get();

        return response()->json([
            'employee' => $employee->first_name . ' ' . $employee->last_name,
            'promotions' => $promotions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);

        $request->validate([
            'previous_position' => 'required|string|max:255',
            'new_position' => 'required|string|max:255',
            'promotion_date' => 'required|date',
        ]);

        $employee->update(['position' => $request->new_position]);
        $promotion = PromotionHistory::create([
            'employee_id' => $employeeId,
            'previous_position' => $request->previous_position,
            'new_position' => $request->new_position,
            'promotion_date' => $request->promotion_date,
        ]);

        return response()->json([
            'message' => 'Promotion history created successfully',
            'promotion' => $promotion
        ]);
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
