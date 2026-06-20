<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\PromotionHistory;
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
            'id',
            'user_id',
            'department_id',
            'first_name',
            'middle_name',
            'surname',
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
                    'username'          => $employee->user->username ?? null,
                    'email'             => $employee->user->email ?? null,
                    'first_name'        => $employee->first_name,
                    'middle_name'       => $employee->middle_name,
                    'surname'           => $employee->surname,
                    'id_number'         => $employee->id_number,
                    'employment_status' => $employee->employment_status,
                    'position'          => $employee->position,
                    'department'        => $employee->department->name ?? null,
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
            'username'                        => 'required|string|unique:users,username',
            'email'                            => 'required|string|email|unique:users,email',
            'password'                         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'first_name'                       => 'required|string',
            'middle_name'                      => 'nullable|string',
            'surname'                          => 'required|string',
            'id_number'                        => 'required|string|unique:employees,id_number',
            'birthdate'                        => 'nullable|date',
            'place_of_birth'                   => 'nullable|string',
            'sex'                              => 'required|in:male,female',
            'civil_status'                     => 'required|in:single,married,widowed,separated',
            'height'                           => 'nullable|string',
            'weight'                           => 'nullable|string',
            'bloodtype'                        => 'nullable|string',
            'highest_educational_attainment'   => 'required|in:elementary,secondary,vocational,college,graduated',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|string',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'employment_status'                => 'required|in:permanent,casual,elected,job_order',
            'position'                         => 'required|string',
            'department_id'                    => 'required|exists:departments,id',
            'date_hired'                       => 'required|date',
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

            // return Employee::create([
            //     'user_id'           => $user->id,
            //     'department_id'     => $request->department_id,
            //     'first_name'        => $request->first_name,
            //     'middle_name'       => $request->middle_name,
            //     'last_name'         => $request->last_name,
            //     'birthdate'         => $request->birthdate,
            //     'contact_number'    => $request->contact_number,
            //     'id_number'         => $request->id_number,
            //     'employment_status' => $request->employment_status,
            //     'position'          => $request->position,
            //     'date_hired'        => $request->date_hired,
            // ]);
            $employee = Employee::create([
                'user_id'                          => $user->id,
                'department_id'                     => $request->department_id,
                'first_name'                        => $request->first_name,
                'middle_name'                        => $request->middle_name,
                'surname'                            => $request->surname,
                'id_number'                          => $request->id_number,
                'birthdate'                          => $request->birthdate,
                'place_of_birth'                     => $request->place_of_birth,
                'sex'                                => $request->sex,
                'civil_status'                       => $request->civil_status,
                'height'                             => $request->height,
                'weight'                             => $request->weight,
                'bloodtype'                          => $request->bloodtype,
                'highest_educational_attainment'     => $request->highest_educational_attainment,
                'residential_address'                => $request->residential_address,
                'contact_number'                     => $request->contact_number,
                'umid_id'                            => $request->umid_id,
                'pagibig_id'                         => $request->pagibig_id,
                'philhealth_number'                  => $request->philhealth_number,
                'psn_number'                         => $request->psn_number,
                'tin_number'                         => $request->tin_number,
                'employment_status'                  => $request->employment_status,
                'position'                           => $request->position,
                'date_hired'                         => $request->date_hired,
            ]);
            EmploymentHistory::create([
                'employee_id'                  => $employee->id,
                'previous_position'            => null,
                'new_position'                 => $employee->position,
                'previous_employment_status'   => null,
                'new_employment_status'        => $employee->employment_status,
                'effective_date'               => $employee->date_hired,
                'remarks'                       => 'Initial employment record',
            ]);

            return $employee;
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
            'surname',
            'id_number',
            'birthdate',
            'place_of_birth',
            'sex',
            'civil_status',
            'height',
            'weight',
            'bloodtype',
            'highest_educational_attainment',
            'residential_address',
            'contact_number',
            'umid_id',
            'pagibig_id',
            'philhealth_number',
            'psn_number',
            'tin_number',
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
                $query->select('id', 'employee_id', 'previous_position', 'new_position', 'previous_employment_status', 'new_employment_status', 'effective_date', 'remarks')
                    ->orderBy('effective_date', 'desc');
            }
        ])->findOrFail($id);

        return response()->json([
            'id'                                => $employee->id,
            'username'                          => $employee->user->username ?? null,
            'email'                              => $employee->user->email ?? null,
            'first_name'                         => $employee->first_name,
            'middle_name'                        => $employee->middle_name,
            'surname'                            => $employee->surname,
            'id_number'                          => $employee->id_number,
            'birthdate'                          => $employee->birthdate,
            'place_of_birth'                     => $employee->place_of_birth,
            'sex'                                => $employee->sex,
            'civil_status'                       => $employee->civil_status,
            'height'                             => $employee->height,
            'weight'                             => $employee->weight,
            'bloodtype'                          => $employee->bloodtype,
            'highest_educational_attainment'     => $employee->highest_educational_attainment,
            'residential_address'                => $employee->residential_address,
            'contact_number'                     => $employee->contact_number,
            'umid_id'                            => $employee->umid_id,
            'pagibig_id'                         => $employee->pagibig_id,
            'philhealth_number'                  => $employee->philhealth_number,
            'psn_number'                         => $employee->psn_number,
            'tin_number'                         => $employee->tin_number,
            'employment_status'                  => $employee->employment_status,
            'position'                           => $employee->position,
            'department'                         => $employee->department->name ?? null,
            'date_hired'                         => $employee->date_hired,
            'employment_history'                 => $employee->employmentHistory,
            'is_active'                          => $employee->is_active,
        ]);
    }


    public function update(Request $request, string $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'first_name'                       => 'sometimes|string',
            'middle_name'                      => 'nullable|string',
            'surname'                          => 'sometimes|string',
            'birthdate'                        => 'nullable|date',
            'place_of_birth'                   => 'nullable|string',
            'sex'                              => 'sometimes|in:male,female',
            'civil_status'                     => 'sometimes|in:single,married,widowed,separated',
            'height'                           => 'nullable|string',
            'weight'                           => 'nullable|string',
            'bloodtype'                        => 'nullable|string',
            'highest_educational_attainment'   => 'sometimes|in:elementary,secondary,vocational,college,graduated',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|string',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'department_id'                    => 'sometimes|exists:departments,id',
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
