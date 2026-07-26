<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

        $departments = Department::select(['id', 'name', 'code', 'is_active'])->where('is_active', true)
            ->orderBy('name')->get();

        return response()->json([
            'message' => 'Departments retrieved successfully',
            'data' => $departments
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:departments,code',
            'is_active' => 'boolean'
        ]);

        $department = Department::create([
            'name' => $request->name,
            'code' => $request->code,
            'is_active' => $request->is_active ?? true


        ]);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'department.created',
            'description'  => "Created department {$department->name}",
            'subject_type' => 'Department',
            'subject_id'   => $department->id,
        ]);

        return response()->json([
            'message' => 'Department created successfully',
            'data'    => $department->only(['id', 'name', 'code', 'is_active'])
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Vertical partitioning
        $department = Department::select(['id', 'name', 'code', 'is_active'])
            ->findOrFail($id);

        return response()->json([
            'message' => 'Department retrieved successfully',
            'data'    => $department
        ]);
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:10|unique:departments,code,' . $id,
            'is_active' => 'boolean'
        ]);
        // Single query: find and update
        $department = Department::findOrFail($id);
        $department->update($validated);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'department.updated',
            'description'  => "Updated department {$department->name}",
            'subject_type' => 'Department',
            'subject_id'   => $department->id,
        ]);


        return response()->json([
            'message' => 'Department updated successfully',
            'data' => $department
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $department = Department::findOrFail($id);
        $department->update(['is_active' => false]);

        ActivityLog::create([
            'user_id'      => request()->user()->id,
            'action'       => 'department.deactivated',
            'description'  => "Deactivated department {$department->name}",
            'subject_type' => 'Department',
            'subject_id'   => $department->id,
        ]);

        return response()->json([
            'message' => 'Department deactivated successfully'
        ], 200);
    }
}
