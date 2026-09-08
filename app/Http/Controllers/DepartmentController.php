<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // List active departments only, sorted by name.
    public function index()
    {

        $departments = Department::select(['id', 'name', 'code', 'is_active'])->where('is_active', true)
            ->orderBy('name')->get();

        return response()->json([
            'message' => 'Departments retrieved successfully',
            'data' => $departments
        ], 200);
    }

    // Create a department and log it. Code must be unique.
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

    // Show one department by ID. 404s if not found.
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


    // Update a department and log it. Only sends fields that were provided.
    public function update(Request $request, string $id)
    {

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:10|unique:departments,code,' . $id,
            'is_active' => 'boolean'
        ]);
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


    // Deactivate a department. Does not delete the row, so employee records keep their link.
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
