<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use App\Models\EmploymentHistory;
use Illuminate\Validation\Rules\Password;


class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */

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
            'employment_history' => function ($query) {
                $query->select('id', 'employee_id', 'previous_position', 'new_position', 'previous_employment_status', 'new_employment_status', 'effective_date', 'remarks')
                    ->orderBy('effective_date', 'desc');
            }
        ])->findOrFail($id);
        // NEW: Calculate Step Increment, Loyalty Pay, Retirement
        $stepIncrementInfo = $this->calculateStepIncrement($employee);
        $loyaltyPayInfo = $this->calculateLoyaltyPay($employee);
        $retirementInfo = $this->calculateRetirement($employee);



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
            'employment_history'                 => $employee->employment_history,
            'step_increment'                     => $stepIncrementInfo,
            'loyalty_pay'                        => $loyaltyPayInfo,
            'retirement'                         => $retirementInfo,
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
            'position'                         => 'sometimes|string',
            'employment_status'                => 'sometimes|in:permanent,casual,elected,job_order',
            'date_hired'                       => 'sometimes|date',
        ]);

        $employee->update($validated);

        return response()->json([
            'message'  => 'Employee updated successfully',
            'employee' => $employee,
        ]);
    }

    // public function calculateStepIncrement($employee)
    // {
    //     $latestReset = $employee->employment_history()
    //         ->where(function ($query) {
    //             $query->where('new_employment_status', 'permanent')
    //                 ->orWhereColumn('new_position', '!=', 'previous_position');
    //         })
    //         ->orderBy('effective_date', 'desc')
    //         ->first();

    //     if (!$latestReset || $employee->employment_status !== 'permanent') {
    //         return [
    //             'current_step' => null,
    //             'message' => 'Not applicable - employee is not permanent',
    //         ];
    //     }

    //     $startDate = \Carbon\Carbon::parse($latestReset->effective_date);
    //     $yearsServed = $startDate->diffInYears(now());

    //     // Safeguard: if startDate is in the future, treat as 0 years served
    //     $yearsServed = max(0, $yearsServed);

    //     $step = min(8, max(1, floor($yearsServed / 3) + 1));

    //     $nextStepDate = $startDate->copy()->addYears($step * 3);

    //     return [
    //         'current_step'    => (int) $step,
    //         'since'           => $startDate->format('Y-m-d'),
    //         'next_step_date'  => $step < 8 ? $nextStepDate->format('Y-m-d') : null,
    //     ];
    // }

    // public function calculateStepIncrement($employee)
    // {
    //     $latestReset = $employee->employment_history()
    //         ->where(function ($query) {
    //             $query->where('new_employment_status', 'permanent')
    //                 ->orWhereColumn('new_position', '!=', 'previous_position');
    //         })
    //         ->orderBy('effective_date', 'desc')
    //         ->first();

    //     if (!$latestReset || $employee->employment_status !== 'permanent') {
    //         return [
    //             'current_step' => null,
    //             'message' => 'Not applicable - employee is not permanent',
    //             'history' => [],
    //         ];
    //     }

    //     $startDate = \Carbon\Carbon::parse($latestReset->effective_date);
    //     $yearsServed = max(0, $startDate->diffInYears(now()));
    //     $step = min(8, max(1, floor($yearsServed / 3) + 1));

    //     $nextStepDate = $startDate->copy()->addYears($step * 3);

    //     // Build full step history (Step 1 up to current step)
    //     $history = [];
    //     for ($i = 1; $i <= $step; $i++) {
    //         $stepDate = $startDate->copy()->addYears(($i - 1) * 3);
    //         $history[] = [
    //             'step' => $i,
    //             'date_reached' => $stepDate->format('Y-m-d'),
    //         ];
    //     }

    //     return [
    //         'current_step'    => (int) $step,
    //         'since'           => $startDate->format('Y-m-d'),
    //         'next_step_date'  => $step < 8 ? $nextStepDate->format('Y-m-d') : null,
    //         'history'         => $history,
    //     ];
    // }
    public function calculateStepIncrement($employee)
    {
        $latestReset = $employee->employment_history()
            ->where(function ($query) {
                $query->where('new_employment_status', 'permanent')
                    ->orWhereColumn('new_position', '!=', 'previous_position');
            })
            ->orderBy('effective_date', 'desc')
            ->first();

        if (!$latestReset || $employee->employment_status !== 'permanent') {
            return [
                'current_step' => null,
                'message' => 'Not applicable - employee is not permanent',
                'all_steps' => [],
            ];
        }

        $startDate = \Carbon\Carbon::parse($latestReset->effective_date);
        $yearsServed = max(0, $startDate->diffInYears(now()));
        $currentStep = min(8, max(1, floor($yearsServed / 3) + 1));

        $nextStepDate = $startDate->copy()->addYears($currentStep * 3);

        // Build ALL 8 steps with their dates (past, current, future)
        $allSteps = [];
        for ($i = 1; $i <= 8; $i++) {
            $stepDate = $startDate->copy()->addYears(($i - 1) * 3);
            $allSteps[] = [
                'step'        => $i,
                'date'        => $stepDate->format('Y-m-d'),
                'status'      => $i < $currentStep ? 'reached' : ($i == $currentStep ? 'current' : 'upcoming'),
            ];
        }

        return [
            'current_step'    => (int) $currentStep,
            'since'           => $startDate->format('Y-m-d'),
            'next_step_date'  => $currentStep < 8 ? $nextStepDate->format('Y-m-d') : null,
            'all_steps'       => $allSteps,
        ];
    }
    private function calculateLoyaltyPay($employee)
    {
        $startDate = \Carbon\Carbon::parse($employee->date_hired);
        $yearsServed = $startDate->diffInYears(now());

        if ($yearsServed < 10) {
            $yearsUntilFirst = 10 - $yearsServed;
            return [
                'eligible' => false,
                'years_served' => $yearsServed,
                'years_until_next' => $yearsUntilFirst,
                'next_milestone' => 10,
            ];
        }

        // After 10 years, every 5 years
        $yearsAfterFirst = $yearsServed - 10;
        $milestonesPassed = floor($yearsAfterFirst / 5) + 1; // +1 for the initial 10-year milestone
        $nextMilestone = 10 + ($milestonesPassed * 5);
        $yearsUntilNext = $nextMilestone - $yearsServed;

        return [
            'eligible' => true,
            'years_served' => $yearsServed,
            'milestones_received' => (int) $milestonesPassed,
            'years_until_next' => $yearsUntilNext,
            'next_milestone' => $nextMilestone,
        ];
    }

    private function calculateRetirement($employee)
    {
        if (!$employee->birthdate) {
            return ['eligible' => false, 'message' => 'No birthdate on record'];
        }

        $birthdate = \Carbon\Carbon::parse($employee->birthdate);
        $age = $birthdate->age;
        $retirementDate = $birthdate->copy()->addYears(65);

        return [
            'current_age'      => $age,
            'retirement_date'  => $retirementDate->format('Y-m-d'),
            'years_remaining'  => $age < 65 ? (65 - $age) : 0,
            'eligible_now'     => $age >= 65,
        ];
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
