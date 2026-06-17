<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;


class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    //OLD Code
    // public function index()
    // {
    //     $employees = Employee::with(['user', 'department'])
    //         ->get()
    //         ->map(function ($employee) {
    //             return [
    //                 'id' => $employee->id,
    //                 'username' => $employee->user->username,
    //                 'email' => $employee->user->email,
    //                 'first_name' => $employee->first_name,
    //                 'middle_name' => $employee->middle_name,
    //                 'last_name' => $employee->last_name,
    //                 'birthdate' => $employee->birthdate,
    //                 'contact_number' => $employee->contact_number,
    //                 'id_number' => $employee->id_number,
    //                 'employment_status' => $employee->employment_status,
    //                 'position' => $employee->position,
    //                 'department' => $employee->department->name,
    //                 'date_hired' => $employee->date_hired,
    //                 'is_active' => $employee->is_active,
    //             ];
    //         });

    //     return response()->json($employees);
    // }

    // 

    //Vertical Partitioning applied to main query and relationship

    public function index()
    {
        $employees = Employee::select([
            'last_name',
            'birthdate',
            'contact_number',
            'id_number',
            'employment_status',
            'position',
            'date_hired',
            'is_active'
        ])
            ->with([
                'user' => function ($query) {
                    $query->select('id', 'username', 'email');
                },
                'department' => function ($query) {
                    $query->select('id', 'name');
                }
            ])
            ->get()
            ->map(function ($employee) {
                return [
                    'id'                => $employee->id,
                    'username'          => $employee->user->username ?? null, // Null Coalescing Safety
                    'email'             => $employee->user->email ?? null,    // Null Coalescing Safety
                    'first_name'        => $employee->first_name,
                    'middle_name'       => $employee->middle_name,
                    'last_name'         => $employee->last_name,
                    'birthdate'         => $employee->birthdate,
                    'contact_number'    => $employee->contact_number,
                    'id_number'         => $employee->id_number,
                    'employment_status' => $employee->employment_status,
                    'position'          => $employee->position,
                    'department'        => $employee->department->name ?? null, // Null Coalescing Safety
                    'date_hired'        => $employee->date_hired,
                    'is_active'         => $employee->is_active,
                ];
            });

        return response()->json($employees);
    }
    // public function store(Request $request)
    // {

    //     $request->validate([
    //         'username' => 'required|string|unique:users,username',
    //         'email' => 'required|string|email|unique:users,email',
    //         'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols(),],
    //         'first_name' => 'required|string',
    //         'middle_name' => 'nullable|string',
    //         'last_name' => 'required|string',
    //         'birthdate' => 'nullable|date',
    //         'contact_number' => 'nullable|string',
    //         'id_number' => 'required|string|unique:employees,id_number',
    //         'employment_status' => "required|in:permanent,casual,elected",
    //         'position' => 'required|string',
    //         'department_id' => 'required|exists:departments,id',
    //         'date_hired' => 'required|date',
    //     ]);

    //     $admin = request()->user();
    //     if (!$admin || $admin->role !== 'hr_admin') {

    //         return response()->json([
    //             'message' => "Forbidden"
    //         ], 403);
    //     }

    //     $user = User::create([
    //         'username' => $request->username,
    //         'email' => $request->email,
    //         'password' => Hash::make($request->password),
    //         'role' => 'employee',
    //     ]);

    //     $employee = Employee::create([
    //         'user_id'           => $user->id,
    //         'department_id'     => $request->department_id,
    //         'first_name'        => $request->first_name,
    //         'middle_name'       => $request->middle_name,
    //         'last_name'         => $request->last_name,
    //         'birthdate'         => $request->birthdate,
    //         'contact_number'    => $request->contact_number,
    //         'id_number'         => $request->id_number,
    //         'employment_status' => $request->employment_status,
    //         'position'          => $request->position,
    //         'date_hired'        => $request->date_hired,
    //     ]);

    //     return response()->json([
    //         'message' => 'Employee created successfully',
    //         'employee' => $employee,
    //     ], 201);
    // }

    // Implemented ACID Database Transaction
    public function store(Request $request)
    {

        $request->validate([
            'username' => 'required|string|unique:users,username',
            'email' => 'required|string|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols(),],
            'first_name' => 'required|string',
            'middle_name' => 'nullable|string',
            'last_name' => 'required|string',
            'birthdate' => 'nullable|date',
            'contact_number' => 'nullable|string',
            'id_number' => 'required|string|unique:employees,id_number',
            'employment_status' => "required|in:permanent,casual,elected",
            'position' => 'required|string',
            'department_id' => 'required|exists:departments,id',
            'date_hired' => 'required|date',
        ]);

        $admin = request()->user();
        if (!$admin || $admin->role !== 'hr_admin') {

            return response()->json([
                'message' => "Forbidden"
            ], 403);
        }

        $employee = DB::transaction(function () use ($request) {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'employee',
            ]);

            return Employee::create([
                'user_id'           => $user->id,
                'department_id'     => $request->department_id,
                'first_name'        => $request->first_name,
                'middle_name'       => $request->middle_name,
                'last_name'         => $request->last_name,
                'birthdate'         => $request->birthdate,
                'contact_number'    => $request->contact_number,
                'id_number'         => $request->id_number,
                'employment_status' => $request->employment_status,
                'position'          => $request->position,
                'date_hired'        => $request->date_hired,
            ]);
        });
        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee,
        ], 201);
    }

    // public function show(string $id)
    // {
    //     $employee = Employee::with(['user', 'department', 'promotion_history'])->findOrFail($id);

    //     return response()->json([
    //         'id' => $employee->id,
    //         'username' => $employee->user->username,
    //         'email' => $employee->user->email,
    //         'first_name' => $employee->first_name,
    //         'middle_name' => $employee->middle_name,
    //         'last_name' => $employee->last_name,
    //         'birthdate' => $employee->birthdate,
    //         'contact_number' => $employee->contact_number,
    //         'id_number' => $employee->id_number,
    //         'employment_status' => $employee->employment_status,
    //         'position' => $employee->position,
    //         'department' => $employee->department->name,
    //         'date_hired' => $employee->date_hired,
    //         'promotion_history' => $employee->promotion_history,
    //         'is_active' => $employee->is_active,
    //     ]);
    // }


    //Vertical Partitioning applied to single record lookups.

    public function show(string $id)
    {
        $employee = Employee::select([
            'id',
            'user_id',
            'department_id',
            'first_name',
            'middle_name',
            'last_name',
            'birthdate',
            'contact_number',
            'id_number',
            'employment_status',
            'position',
            'date_hired',
            'is_active'
        ])->with([
            'user' => function ($query) {
                $query->select('id', 'username', 'email');
            },
            'department' => function ($query) {
                $query->select('id', 'name');
            },
            'promotion_history' => function ($query) {
                $query->select('id', 'previous_position', 'new_position', 'promotion_date');
            }
        ])->findorFail($id);

        return response()->json([
            'id'                => $employee->id,
            'username'          => $employee->user->username ?? null,
            'email'             => $employee->user->email ?? null,
            'first_name'        => $employee->first_name,
            'middle_name'       => $employee->middle_name,
            'last_name'         => $employee->last_name,
            'birthdate'         => $employee->birthdate,
            'contact_number'    => $employee->contact_number,
            'id_number'         => $employee->id_number,
            'employment_status' => $employee->employment_status,
            'position'          => $employee->position,
            'department'        => $employee->department->name ?? null,
            'date_hired'        => $employee->date_hired,
            'promotion_history' => $employee->promotion_history,
            'is_active'         => $employee->is_active,
        ]);
    }


    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'first_name'        => 'sometimes|string',
            'middle_name'       => 'nullable|string',
            'last_name'         => 'sometimes|string',
            'birthdate'         => 'nullable|date',
            'contact_number'    => 'nullable|string',
            'employment_status' => 'sometimes|in:permanent,casual,elected',
            'position'          => 'sometimes|string',
            'department_id'     => 'sometimes|exists:departments,id',
            'date_hired'        => 'sometimes|date',
        ]);

        $employee->update($validated);

        return response()->json([
            'message'  => 'Employee updated successfully',
            'employee' => $employee,
        ]);
    }

    public function destroy(string $id)
    {

        $user = request()->user();

        if (!$user || $user->role !== "hr_admin") {

            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }
        $employee = Employee::findOrFail($id);

        $employee->update(['is_active' => false]);
        return response()->json([
            'message' => 'Employee deactivated successfully',
        ]);
    }
}
