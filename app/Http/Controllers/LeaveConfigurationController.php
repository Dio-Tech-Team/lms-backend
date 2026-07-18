<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveConfiguration;

class LeaveConfigurationController extends Controller
{
    public function index()
    {
        // Vertical partitioning - no SELECT *
        $configs = LeaveConfiguration::select([
            'id',
            'name',
            'code',
            'application_to',
            'can_carry_over',
            'can_monetize',
            'fixed_days',
            'monthly_credit',
            'credit_type',
            'description',
        ])->get();

        return response()->json($configs);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'code'           => 'required|string|max:50|unique:leave_configurations,code',
            // 'application_to' => 'required|in:permanent,casual,elected,job_order,all',
            'application_to' => 'required|array',
            'can_carry_over' => 'boolean',
            'can_monetize'   => 'boolean',
            'fixed_days'     => 'nullable|numeric',
            'monthly_credit' => 'nullable|numeric',
            'credit_type'    => 'nullable|in:fixed,monthly',
            'description'    => 'nullable|string',
        ]);
        $validated['application_to'] = implode(',', $request->application_to);
        $config = LeaveConfiguration::create($validated);

        return response()->json([
            'message' => 'Leave configuration created successfully',
            'data'    => $config->only([
                'id',
                'name',
                'code',
                'application_to',
                'can_carry_over',
                'can_monetize',
                'fixed_days',
                'monthly_credit',
                'credit_type',
                'description'
            ])
        ], 201);
    }

    public function show(string $id)
    {
        // Vertical partitioning
        $config = LeaveConfiguration::select([
            'id',
            'name',
            'code',
            'application_to',
            'can_carry_over',
            'can_monetize',
            'fixed_days',
            'monthly_credit',
            'credit_type',
            'description',
        ])->findOrFail($id);

        return response()->json($config);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'name'           => 'sometimes|required|string|max:255',
            'code'           => 'sometimes|required|string|max:50|unique:leave_configurations,code,' . $id,
            // 'application_to' => 'sometimes|required|in:permanent,casual,elected,job_order,all',
            'application_to' => 'sometimes|required|array',
            'application_to.*' => 'in:permanent,casual,elected,job_order,all',
            'can_carry_over' => 'sometimes|boolean',
            'can_monetize'   => 'sometimes|boolean',
            'fixed_days'     => 'nullable|numeric',
            'monthly_credit' => 'nullable|numeric',
            'credit_type'    => 'nullable|in:fixed,monthly',
            'description'    => 'nullable|string',
        ]);
        if ($request->has('application_to')) {
            $validated['application_to'] = implode(',', $request->application_to);
        }

        $config = LeaveConfiguration::findOrFail($id);
        $config->update($validated);

        return response()->json([
            'message' => 'Leave configuration updated successfully',
            'data'    => $config->only([
                'id',
                'name',
                'code',
                'application_to',
                'can_carry_over',
                'can_monetize',
                'fixed_days',
                'monthly_credit',
                'credit_type',
                'description'
            ])
        ]);
    }

    public function destroy(string $id)
    {
        // OPTIMIZED: single query instead of findOrFail + delete
        $affected = LeaveConfiguration::where('id', $id)->delete();

        if (!$affected) {
            return response()->json(['message' => 'Leave configuration not found'], 404);
        }

        return response()->json(['message' => 'Leave configuration deleted successfully']);
    }
}
