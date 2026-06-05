<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveConfiguration;

class LeaveConfigurationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $config = LeaveConfiguration::all();
        return response()->json($config);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:leave_configurations,code',
            'application_to' => 'required|in:permanent,casual,elected,all',
            'can_carry_over' => 'boolean',
            'can_monetize' => 'boolean',
            'fixed_days' => 'nullable|numeric',
            'monthly_credit' => 'nullable|numeric',
            'credit_type' => 'nullable|in:fixed,monthly',
            'description' => 'nullable|string',
        ]);

        $config = LeaveConfiguration::create($validatedData);

        return response()->json([
            'message' => 'Leave configuration created successfully',
            'data' => $config
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $config = LeaveConfiguration::findOrFail($id);
        return response()->json($config);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $config = LeaveConfiguration::findOrFail($id);

        $validateData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:leave_configurations,code,' . $id,
            'application_to' => 'sometimes|required|in:permanent,casual,elected,all',
            'can_carry_over' => 'sometimes|boolean',
            'can_monetize' => 'sometimes|boolean',
            'fixed_days' => 'nullable|numeric',
            'monthly_credit' => 'nullable|numeric',
            'credit_type' => 'nullable|in:fixed,monthly',
            'description' => 'nullable|string',
        ]);

        $config->update($validateData);

        return response()->json([
            'message' => 'Leave configuration updated successfully',
            'data' => $config
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $config = LeaveConfiguration::findOrFail($id);

        $config->delete();

        return response()->json([
            'message' => 'Leave configuration deleted successfully'
        ]);
    }
}
