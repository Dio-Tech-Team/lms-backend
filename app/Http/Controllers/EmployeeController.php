<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Employee;
// use Illuminate\Auth\Events\Registered;
use App\Models\EmploymentHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\LeaveConfiguration;
use App\Http\Controllers\LeaveCreditController;
use App\Models\LeaveApplication;
use App\Models\LeaveRecord;
use App\Models\Attendance;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use Carbon\Carbon;


class EmployeeController extends Controller
{
    public function index(Request $request)
    {

        $user = $request->user();

        $query = Employee::select([
            'employees.id',
            'employees.first_name',
            'employees.middle_name',
            'employees.surname',
            'employees.id_number',
            'employees.employment_status',
            'employees.position',
            'employees.date_hired',
            'employees.is_active',
            'users.username',
            'employees.sex',
            'users.email',
            'departments.name as department_name',
        ])
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id');


        if (!in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            $query->where('employees.user_id', $user->id);
        }

        // Apply department filter if present in request
        if ($request->filled('department_id')) {
            $query->where('employees.department_id', $request->department_id);
        }

        if ($request->filled('sex')) {
            $query->where('employees.sex', $request->sex);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employees.first_name', 'LIKE', "%{$search}%")
                    ->orWhere('employees.surname', 'LIKE', "%{$search}%")
                    ->orWhere('employees.id_number', 'LIKE', "%{$search}%");
            });
        }
        // Apply pagination
        $query->orderBy('employees.surname')
            ->orderBy('employees.first_name');
        $employees = $query->paginate(10);


        // Get IDs of employees currently on approved leave — single query, not per-row
        $onLeaveIds = LeaveApplication::where('status', 'approved')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->pluck('employee_id')
            ->toArray();

        // Tag each employee in the paginated collection
        $employees->getCollection()->transform(function ($employee) use ($onLeaveIds) {
            $employee->is_on_leave = in_array($employee->id, $onLeaveIds);
            return $employee;
        });


        return response()->json($employees);
    }

    public function stats()
    {
        $total     = Employee::count();
        $active    = Employee::where('is_active', true)->count();
        $inactive  = Employee::where('is_active', false)->count();
        $permanent = Employee::where('employment_status', 'permanent')->count();
        $casual    = Employee::where('employment_status', 'casual')->count();

        $departmentBreakdown = Employee::select('departments.name as department_name')
            ->selectRaw('count(*) as count')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->groupBy('departments.name')
            ->orderByDesc('count')
            // ->limit(6)
            ->get();

        return response()->json([
            'total'                => $total,
            'active'               => $active,
            'inactive'             => $inactive,
            'permanent'            => $permanent,
            'casual'               => $casual,
            'department_breakdown' => $departmentBreakdown,
        ]);
    }
    // Implemented ACID Database Transaction
    public function store(Request $request)
    {

        $request->validate([
            'username'                        => 'required|string|unique:users,username',
            // 'email'                            => 'required|string|email|unique:users,email',
            'email'                            => 'nullable|string|email|unique:users,email',
            // 'password'                         => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
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
            'highest_educational_attainment'   => 'required|in:elementary,secondary,vocational,college,graduate',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|string',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'employment_status'                => 'required|in:permanent,casual,elected,job_order,resigned',
            // 'position'                         => 'required|string',
            'position' => 'required|string|exists:positions,title',
            'department_id'                    => 'required|exists:departments,id',
            'date_hired'                       => 'required|date',
        ]);

        // Authorization check before DB transaction
        $admin = request()->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $employee = DB::transaction(function () use ($request) {
            $defaultPassword = $request->id_number;

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email, // nullable now — no placeholder fallback
                'password' => Hash::make($defaultPassword),
                'role' => 'employee',
                'must_change_password' => true,
            ]);

            // $employee = DB::transaction(function () use ($request) {
            //     $user = User::create([
            //         'username' => $request->username,
            //         'email' => $request->email,
            //         'password' => Hash::make($request->password),
            //         'role' => 'employee',
            //     ]);

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

            // NEW — log the registration
            ActivityLog::create([
                'user_id'      => request()->user()->id,
                'action'       => 'employee.registered',
                'description'  => "Registered employee {$employee->first_name} {$employee->surname}",
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);
            try {
                $creditController = new LeaveCreditController();
                $hireYear = Carbon::parse($employee->date_hired)->year;
                $currentYear = now()->year;

                // 1. Initialize for the year they were hired
                $creditController->initializeSingleEmployeeCredits($employee->id, $hireYear);

                // 2. If hired in a past year, also initialize their credits for the current year
                if ($hireYear !== $currentYear) {
                    $creditController->initializeSingleEmployeeCredits($employee->id, $currentYear);
                }
            } catch (\Exception $e) {
                // Log issues but don't fail the entire transaction if leave module fails
                Log::error("Leave credit initialization failed during registration: " . $e->getMessage());
            }

            return $employee;
        });
        return response()->json([
            'message' => 'Employee created successfully',
            'employee' => $employee,
            'user' => $employee->user,
            // 'user' => $employee->user()->select('username', 'email')->first(),
            'default_password' => $request->id_number,
        ], 201);
    }


    public function show(string $id, Request $request)
    {
        $employee = Employee::select([
            'employees.id',
            'employees.user_id',
            'employees.department_id',
            'employees.first_name',
            'employees.middle_name',
            'employees.surname',
            'employees.id_number',
            'employees.birthdate',
            'employees.place_of_birth',
            'employees.sex',
            'employees.civil_status',
            'employees.height',
            'employees.weight',
            'employees.bloodtype',
            'employees.highest_educational_attainment',
            'employees.residential_address',
            'employees.contact_number',
            'employees.umid_id',
            'employees.pagibig_id',
            'employees.philhealth_number',
            'employees.psn_number',
            'employees.tin_number',
            'employees.employment_status',
            'employees.position',
            'employees.date_hired',
            'employees.is_active',
            'users.username',
            'users.email',
            'departments.name as department_name',
        ])
            ->join('users', 'employees.user_id', '=', 'users.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->with([
                'employment_history' => function ($query) {
                    $query->select(
                        'id',
                        'employee_id',
                        'previous_position',
                        'new_position',
                        'previous_employment_status',
                        'new_employment_status',
                        'effective_date',
                        'remarks'
                    )->orderBy('effective_date', 'desc')
                        ->orderBy('id', 'desc');
                }
            ])
            ->findOrFail($id);

        $user = $request->user();
        if (!in_array($user->role, ['hr_admin', 'super_admin'], true) && $employee->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $stepIncrementInfo = $this->calculateStepIncrement($employee);
        $loyaltyPayInfo    = $this->calculateLoyaltyPay($employee);
        $retirementInfo    = $this->calculateRetirement($employee);
        $isOnLeave         = $this->calculateOnLeaveStatus($employee);

        return response()->json([
            'id'                             => $employee->id,
            'username'                       => $employee->username,
            'email'                          => $employee->email,
            'first_name'                     => $employee->first_name,
            'middle_name'                    => $employee->middle_name,
            'surname'                        => $employee->surname,
            'id_number'                      => $employee->id_number,
            'birthdate'                      => $employee->birthdate,
            'place_of_birth'                 => $employee->place_of_birth,
            'sex'                            => $employee->sex,
            'civil_status'                   => $employee->civil_status,
            'height'                         => $employee->height,
            'weight'                         => $employee->weight,
            'bloodtype'                      => $employee->bloodtype,
            'highest_educational_attainment' => $employee->highest_educational_attainment,
            'residential_address'            => $employee->residential_address,
            'contact_number'                 => $employee->contact_number,
            'umid_id'                        => $employee->umid_id,
            'pagibig_id'                     => $employee->pagibig_id,
            'philhealth_number'              => $employee->philhealth_number,
            'psn_number'                     => $employee->psn_number,
            'tin_number'                     => $employee->tin_number,
            'employment_status'              => $employee->employment_status,
            'position'                       => $employee->position,
            'department_id'                  => $employee->department_id,
            'department'                     => $employee->department_name,
            'date_hired'                     => $employee->date_hired,
            'employment_history'             => $employee->employment_history,
            'step_increment'                 => $stepIncrementInfo,
            'loyalty_pay'                    => $loyaltyPayInfo,
            'retirement'                     => $retirementInfo,
            'is_active'                      => $employee->is_active,
            'is_on_leave'                    => $isOnLeave,
        ]);
    }

    public function update(Request $request, string $id)
    {

        // ADD THIS BLOCK — matches destroy()'s pattern
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

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
            'highest_educational_attainment'   => 'sometimes|in:elementary,secondary,vocational,college,graduate',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|string',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'department_id'                    => 'sometimes|exists:departments,id',
            'position' => 'sometimes|string|exists:positions,title',
            'date_hired'                       => 'sometimes|date',
            // 'email'                            => 'nullable|email|unique:users,email,' . $employee->user_id,
        ]);

        // // Separate email out before updating Employee fields
        // $email = $validated['email'] ?? null;
        // unset($validated['email']);

        // $employee = Employee::findOrFail($id);
        $employee->update($validated);


        // // NEW — update email on the related User record, if provided
        // if (array_key_exists('email', $request->all())) {
        //     $employee->user()->update(['email' => $email]);
        // }

        // NEW — log the update
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'employee.updated',
            'description'  => "Updated employee {$employee->first_name} {$employee->surname}",
            'subject_type' => 'Employee',
            'subject_id'   => $employee->id,
        ]);

        return response()->json([
            'message'  => 'Employee updated successfully',
            'employee' => $employee,
        ]);
    }

    public function calculateStepIncrement($employee)
    {
        $employmentHistory = $employee->relationLoaded('employment_history')
            ? $employee->employment_history
            : $employee->employment_history()->orderBy('effective_date', 'desc')->get();

        $latestReset = $employmentHistory->first(function ($history) {
            return $history->new_employment_status === 'permanent'
                || $history->new_position !== $history->previous_position;
        });

        // if (!$latestReset || $employee->employment_status !== 'permanent') {
        //     return [
        //         'current_step' => null,
        //         'message'      => 'Not applicable - employee is not permanent',
        //         'all_steps'    => [],
        //     ];
        // }
        if ($employee->employment_status !== 'permanent') {
            return [
                'current_step' => null,
                'message'      => 'Not applicable - employee is not permanent',
                'all_steps'    => [],
            ];
        }

        $startDate   = $latestReset
            ? \Carbon\Carbon::parse($latestReset->effective_date)
            : \Carbon\Carbon::parse($employee->date_hired);
        $currentStep = $this->stepAsOf($startDate, now());
        // $yearsServed = max(0, $startDate->diffInYears(now()));
        // $currentStep = min(8, floor($yearsServed / 3) + 1);
        $nextStepDate = $startDate->copy()->addYears($currentStep * 3);

        $allSteps = [];
        for ($i = 1; $i <= 8; $i++) {
            $stepDate   = $startDate->copy()->addYears(($i - 1) * 3);
            $allSteps[] = [
                'step'   => $i,
                'date'   => $stepDate->format('Y-m-d'),
                'status' => $i < $currentStep ? 'reached' : ($i === $currentStep ? 'current' : 'upcoming'),
            ];
        }

        return [
            'current_step'   => (int) $currentStep,
            'since'          => $startDate->format('Y-m-d'),
            'next_step_date' => $currentStep < 8 ? $nextStepDate->format('Y-m-d') : null,
            'all_steps'      => $allSteps,
        ];
    }

    private function stepAsOf($baseDate, $asOfDate): int
    {
        $yearsServed = max(0, $baseDate->diffInYears($asOfDate));
        return (int) min(8, floor($yearsServed / 3) + 1);
    }
    private function calculateLoyaltyPay($employee)
    {
        if ($employee->employment_status !== 'permanent') {
            return [
                'eligible'          => false,
                'message'           => 'Not applicable - employee is not currently permanent',
                'years_served'      => 0,
                'years_until_next'  => null,
                'next_milestone'    => 10,
            ];
        }

        $employmentHistory = $employee->relationLoaded('employment_history')
            ? $employee->employment_history
            : $employee->employment_history()->orderBy('effective_date', 'desc')->get();

        // Find the most recent resignation, if any — loyalty service resets after it
        $latestResignation = $employmentHistory
            ->filter(fn($history) => $history->new_employment_status === 'resigned')
            ->sortByDesc('effective_date')
            ->first();

        $permanentRecords = $employmentHistory
            ->filter(fn($history) => $history->new_employment_status === 'permanent');

        // If they resigned at some point, only count permanent records AFTER that resignation
        if ($latestResignation) {
            $resignationDate = \Carbon\Carbon::parse($latestResignation->effective_date);
            $permanentRecords = $permanentRecords->filter(
                fn($history) => \Carbon\Carbon::parse($history->effective_date)->gte($resignationDate)
            );
        }

        $earliestPermanent = $permanentRecords->sortBy('effective_date')->first();

        $startDate = $earliestPermanent
            ? \Carbon\Carbon::parse($earliestPermanent->effective_date)
            : \Carbon\Carbon::parse($employee->date_hired);

        $yearsServed = max(0, $startDate->diffInYears(now()));

        if ($yearsServed < 10) {
            $yearsUntilFirst = 10 - $yearsServed;
            return [
                'eligible'             => false,
                'since'                => $startDate->format('Y-m-d'),
                'years_served'         => $yearsServed,
                'milestones_received'  => 0,
                'years_until_next'     => $yearsUntilFirst,
                'next_milestone'       => 10,
            ];
        }

        $yearsAfterFirst = $yearsServed - 10;
        $milestonesPassed = floor($yearsAfterFirst / 5) + 1;
        $nextMilestone = 10 + ($milestonesPassed * 5);
        $yearsUntilNext = $nextMilestone - $yearsServed;

        return [
            'eligible'             => true,
            'since'                => $startDate->format('Y-m-d'),
            'years_served'         => $yearsServed,
            'milestones_received'  => (int) $milestonesPassed,
            'years_until_next'     => $yearsUntilNext,
            'next_milestone'       => $nextMilestone,
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

    private function calculateOnLeaveStatus($employee): bool
    {
        return LeaveApplication::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->exists();
    }
    public function leaveCard(string $id, Request $request)
    {
        $employee = Employee::select('id', 'user_id', 'first_name', 'surname', 'position')->findOrFail($id);

        // NEW — block access unless HR/admin or viewing own record
        $user = $request->user();
        if (!in_array($user->role, ['hr_admin', 'super_admin'], true) && $employee->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }


        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
        $slConfig = LeaveConfiguration::where('code', 'SL')->first();

        return response()->json([
            'employee' => [
                'id'       => $employee->id,
                'name'     => $employee->first_name . ' ' . $employee->surname,
                'position' => $employee->position,
            ],
            'vacation_leave' => $this->buildLeaveCardForType($employee, $vlConfig, 'vl'),
            'sick_leave'     => $this->buildLeaveCardForType($employee, $slConfig, 'sl'),
        ]);
    }
    // private function buildLeaveCardForType(Employee $employee, ?LeaveConfiguration $config, string $type): array
    // {
    //     $entries = [];

    //     if ($config) {
    //         $credit = \App\Models\LeaveCredit::where('employee_id', $employee->id)
    //             ->where('leave_configuration_id', $config->id)
    //             ->where('year', now()->year)
    //             ->first();

    //         if ($credit && (float) $credit->opening_balance > 0) {
    //             $openingDate = \Carbon\Carbon::parse($employee->date_hired)->subDay();
    //             $entries[] = [
    //                 'sort_date'   => \Carbon\Carbon::parse($employee->date_hired)->subDay(),
    //                 'period'      => $openingDate->format('m-d-y'),
    //                 'particulars' => 'Transferred from physical leave card',
    //                 'earned'      => round((float) $credit->opening_balance, 3),
    //                 'abs_wp'      => 0,
    //                 'abs_wop'     => 0,
    //                 'used'        => 0,
    //             ];
    //         }

    //         if ($credit && (float) $credit->opening_balance > 0) {
    //             $delta = round((float) $credit->total_credits - (float) $credit->opening_balance, 3);

    //             if ($delta !== 0.0) {
    //                 $correctionLog = ActivityLog::where('subject_type', 'LeaveCredit')
    //                     ->where('subject_id', $credit->id)
    //                     ->where('action', 'leave_credit.updated')
    //                     ->orderByDesc('created_at')
    //                     ->with('user:id,username')
    //                     ->first();

    //                 $correctionDate = $correctionLog
    //                     ? \Carbon\Carbon::parse($correctionLog->created_at)
    //                     : \Carbon\Carbon::parse($credit->last_updated ?? now());

    //                 $actor = $correctionLog?->user?->username ?? 'HR Admin';

    //                 $entries[] = [
    //                     'sort_date'   => $correctionDate,
    //                     'period'      => $correctionDate->format('m-d-y'),
    //                     'particulars' => "Balance correction by{$actor}"
    //                         . ($delta > 0 ? ' (increase)' : ' (decrease)'),
    //                     'earned'      => $delta > 0 ? $delta : 0,
    //                     'abs_wp'      => 0,
    //                     'abs_wop'     => 0,
    //                     'used'        => $delta < 0 ? abs($delta) : 0,
    //                 ];
    //             }
    //         }
    //     }

    //     $attendanceRows = Attendance::where('employee_id', $employee->id)->get();

    //     foreach ($attendanceRows as $row) {
    //         $earned = $type === 'vl'
    //             ? $row->vl_earned - $row->tardiness_equivalent_days
    //             : $row->sl_earned;

    //         $entries[] = [
    //             'sort_date'   => \Carbon\Carbon::parse("{$row->month} 1, {$row->year}"),
    //             'period'      => "{$row->month} {$row->year}",
    //             'particulars' => 'Monthly credit',
    //             'earned'      => round($earned, 3),
    //             'abs_wp'      => (float) $row->absent_with_leave_days,
    //             'abs_wop'     => (float) $row->absent_without_leave_days,
    //             'used'        => 0,
    //         ];
    //     }

    //     if ($config) {
    //         $leaveRecords = LeaveRecord::where('employee_id', $employee->id)
    //             ->where('leave_configuration_id', $config->id)
    //             ->get();

    //         foreach ($leaveRecords as $rec) {
    //             $start   = \Carbon\Carbon::parse($rec->start_date);
    //             $end     = \Carbon\Carbon::parse($rec->end_date);
    //             $withPay = (float) $rec->days_taken - (float) $rec->no_pay_days;

    //             $entries[] = [
    //                 'sort_date'   => $start,
    //                 'period'      => $start->format('m-d-y') . ' to ' . $end->format('m-d-y'),
    //                 'particulars' => $config->name . ' taken',
    //                 'earned'      => 0,
    //                 'abs_wp'      => round($withPay, 3),
    //                 'abs_wop'     => round((float) $rec->no_pay_days, 3),
    //                 'used'        => round($withPay, 3),
    //             ];
    //         }

    //         $monetizations = \App\Models\LeaveMonetization::where('employee_id', $employee->id)
    //             ->where('leave_configuration_id', $config->id)
    //             ->where('status', 'approved')
    //             ->get();

    //         foreach ($monetizations as $mon) {
    //             $date = \Carbon\Carbon::parse($mon->reviewed_at ?? $mon->applied_at);

    //             $entries[] = [
    //                 'sort_date'   => $date,
    //                 'period'      => $date->format('m-d-y'),
    //                 'particulars' => $config->name . ' monetized',
    //                 'earned'      => 0,
    //                 'abs_wp'      => 0,
    //                 'abs_wop'     => 0,
    //                 'used'        => round((float) $mon->days_monetized, 3),
    //             ];
    //         }
    //     }

    //     usort($entries, fn($a, $b) => $a['sort_date'] <=> $b['sort_date']);

    //     $balance = 0;
    //     foreach ($entries as &$entry) {
    //         $balance += $entry['earned'] - $entry['used'];
    //         $entry['balance'] = round($balance, 3);
    //         unset($entry['sort_date']);
    //     }

    //     return $entries;
    // }
    private function buildLeaveCardForType(Employee $employee, ?LeaveConfiguration $config, string $type): array
    {
        $entries = [];

        $credit = null;
        if ($config) {
            $credit = \App\Models\LeaveCredit::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id)
                ->where('year', now()->year)
                ->first();
        }

        // Attendance, leave records, monetizations built first now —
        // opening balance / correction need these to determine sort_date
        $attendanceRows = Attendance::where('employee_id', $employee->id)->get();

        foreach ($attendanceRows as $row) {
            $earned = $type === 'vl'
                ? $row->vl_earned - $row->tardiness_equivalent_days
                : $row->sl_earned;

            $creditDate = \Carbon\Carbon::parse("{$row->month} 1, {$row->year}");


            $entries[] = [
                'sort_date'   => $creditDate,
                'period'      => $creditDate->format('m-d-y') . ' (' . $creditDate->format('M') . ')',
                'particulars' => 'Monthly credit',
                'earned'      => round($earned, 3),
                'abs_wp'      => (float) $row->absent_with_leave_days,
                'abs_wop'     => (float) $row->absent_without_leave_days,
                'used'        => 0,
            ];
        }

        if ($config) {
            $leaveRecords = LeaveRecord::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id)
                ->get();

            foreach ($leaveRecords as $rec) {
                $start   = \Carbon\Carbon::parse($rec->start_date);
                $end     = \Carbon\Carbon::parse($rec->end_date);
                $withPay = (float) $rec->days_taken - (float) $rec->no_pay_days;

                $entries[] = [
                    'sort_date'   => $start,
                    'period'      => $start->format('m-d-y') . ' to ' . $end->format('m-d-y'),
                    'particulars' => $config->name . ' taken',
                    'earned'      => 0,
                    'abs_wp'      => round($withPay, 3),
                    'abs_wop'     => round((float) $rec->no_pay_days, 3),
                    'used'        => round($withPay, 3),
                ];
            }

            $monetizations = \App\Models\LeaveMonetization::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id)
                ->where('status', 'approved')
                ->get();

            foreach ($monetizations as $mon) {
                $date = \Carbon\Carbon::parse($mon->reviewed_at ?? $mon->applied_at);

                $entries[] = [
                    'sort_date'   => $date,
                    'period'      => $date->format('m-d-y'),
                    'particulars' => $config->name . ' monetized',
                    'earned'      => 0,
                    'abs_wp'      => 0,
                    'abs_wop'     => 0,
                    'used'        => round((float) $mon->days_monetized, 3),
                ];
            }
        }

        // Opening balance / correction now built LAST, using the earliest
        // date among all other entries so this always sorts first regardless
        // of whether attendance was backfilled earlier than date_hired
        if ($config && $credit) {
            $earliestOtherDate = collect($entries)->min('sort_date');
            $baseDate = $earliestOtherDate
                ? $earliestOtherDate->copy()->subDay()
                : \Carbon\Carbon::parse($employee->date_hired)->subDay();

            if ((float) $credit->opening_balance > 0) {
                $entries[] = [
                    'sort_date'   => $baseDate,
                    'period'      => $baseDate->format('m-d-y'),
                    'particulars' => 'Transferred from physical leave card',
                    'earned'      => round((float) $credit->opening_balance, 3),
                    'abs_wp'      => 0,
                    'abs_wop'     => 0,
                    'used'        => 0,
                ];

                $delta = round((float) $credit->total_credits - (float) $credit->opening_balance, 3);

                if ($delta !== 0.0) {
                    $correctionLog = ActivityLog::where('subject_type', 'LeaveCredit')
                        ->where('subject_id', $credit->id)
                        ->where('action', 'leave_credit.updated')
                        ->orderByDesc('created_at')
                        ->with('user:id,username')
                        ->first();

                    $correctionDate = $correctionLog
                        ? \Carbon\Carbon::parse($correctionLog->created_at)
                        : \Carbon\Carbon::parse($credit->last_updated ?? now());

                    $actor = $correctionLog?->user?->username ?? 'HR Admin';

                    $entries[] = [
                        'sort_date'   => $correctionDate,
                        'period'      => $correctionDate->format('m-d-y'),
                        'particulars' => "Balance correction"
                            . ($delta > 0 ? ' (increase)' : ' (decrease)'),
                        'earned'      => $delta > 0 ? $delta : 0,
                        'abs_wp'      => 0,
                        'abs_wop'     => 0,
                        'used'        => $delta < 0 ? abs($delta) : 0,
                    ];
                }
            }
        }

        usort($entries, fn($a, $b) => $a['sort_date'] <=> $b['sort_date']);

        $balance = 0;
        foreach ($entries as &$entry) {
            $balance += $entry['earned'] - $entry['used'];
            $entry['balance'] = round($balance, 3);
            unset($entry['sort_date']);
        }

        return $entries;
    }
    public function stepIncrementForecast(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2099',
        ]);

        $forecastYear = (int) $request->input('year');

        // Fetch permanent employees along with their sorted employment history
        $employees = Employee::where('employment_status', 'permanent')
            ->where('is_active', true)
            ->with(['department', 'employment_history' => function ($q) {
                $q->orderBy('effective_date', 'desc');
            }])
            ->get();

        $forecastRecords = [];

        foreach ($employees as $employee) {
            // Find the milestone date where their step reset due to permanency or promotion
            $latestReset = $employee->employment_history->first(function ($history) {
                return $history->new_employment_status === 'permanent'
                    || $history->new_position !== $history->previous_position;
            });

            // Fallback to date_hired if no history record exists
            $baseDate = $latestReset
                ? Carbon::parse($latestReset->effective_date)
                : ($employee->date_hired ? Carbon::parse($employee->date_hired) : null);

            if (!$baseDate) continue;
            // Step just before the forecast year begins, and just before it ends
            $stepBeforeYear = $this->stepAsOf($baseDate, Carbon::create($forecastYear - 1, 12, 31));
            $stepAfterYear  = $this->stepAsOf($baseDate, Carbon::create($forecastYear, 12, 31));

            // Only include employees whose step actually changes during this forecast year
            if ($stepAfterYear > $stepBeforeYear) {
                // The anniversary that triggers the new step: baseDate + (stepAfterYear-1)*3 years
                $nextStepDate = $baseDate->copy()->addYears(($stepAfterYear - 1) * 3);

                $forecastRecords[] = [
                    'employee_id'    => $employee->id,
                    'name'           => "{$employee->first_name} {$employee->surname}",
                    'position'       => $employee->position,
                    'department'     => $employee->department?->name ?? 'Unassigned',
                    'current_step'   => $stepBeforeYear,
                    'next_step'      => $stepAfterYear,
                    'next_step_date' => $nextStepDate->format('Y-m-d'),
                ];
            }
        }

        return response()->json([
            'year'      => $forecastYear,
            'employees' => $forecastRecords
        ]);
    }

    public function resign(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'effective_date' => 'required|date',
            'remarks'        => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($id);

        if ($employee->employment_status === 'resigned') {
            return response()->json(['message' => 'Employee is already marked as resigned'], 422);
        }

        DB::transaction(function () use ($employee, $request, $user) {
            EmploymentHistory::create([
                'employee_id'                 => $employee->id,
                'previous_position'           => $employee->position,
                'new_position'                => $employee->position,
                'previous_employment_status'  => $employee->employment_status,
                'new_employment_status'       => 'resigned',
                'effective_date'              => $request->effective_date,
                'remarks'                     => $request->remarks ?? 'Resignation',
            ]);

            $employee->update([
                'employment_status' => 'resigned',
                'is_active'          => false,
            ]);

            ActivityLog::create([
                'user_id'      => $user->id,
                'action'       => 'employee.resigned',
                'description'  => "Marked {$employee->first_name} {$employee->surname} as resigned effective {$request->effective_date}",
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);
        });

        return response()->json(['message' => 'Employee marked as resigned successfully']);
    }
    public function retire(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'retirement_type' => 'required|in:mandatory,optional',
            'effective_date'  => 'required|date',
            'remarks'         => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($id);

        if ($employee->employment_status === 'retired') {
            return response()->json(['message' => 'Employee is already marked as retired'], 422);
        }

        // Mandatory retirement is age-gated per CSC rules; optional has no age
        // restriction (agency confirmed employees may retire anytime)
        if ($request->retirement_type === 'mandatory') {
            if (!$employee->birthdate) {
                return response()->json(['message' => 'Cannot process mandatory retirement — employee has no birthdate on record'], 422);
            }

            $age = \Carbon\Carbon::parse($employee->birthdate)->age;
            if ($age < 65) {
                return response()->json(['message' => 'Employee is not yet 65 — mandatory retirement is not applicable'], 422);
            }
        }

        DB::transaction(function () use ($employee, $request, $user) {
            EmploymentHistory::create([
                'employee_id'                 => $employee->id,
                'previous_position'           => $employee->position,
                'new_position'                => $employee->position,
                'previous_employment_status'  => $employee->employment_status,
                'new_employment_status'       => 'retired',
                'effective_date'              => $request->effective_date,
                'remarks'                     => $request->remarks
                    ?? ucfirst($request->retirement_type) . ' retirement',
            ]);

            $employee->update([
                'employment_status' => 'retired',
                'retirement_type'   => $request->retirement_type,
                'is_active'          => false,
            ]);

            ActivityLog::create([
                'user_id'      => $user->id,
                'action'       => 'employee.retired',
                'description'  => "Marked {$employee->first_name} {$employee->surname} as {$request->retirement_type} retirement effective {$request->effective_date}",
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);
        });

        return response()->json(['message' => 'Employee marked as retired successfully']);
    }
    public function rehire(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'employment_status' => 'required|in:permanent,casual,elected,job_order',
            // 'position'           => 'required|string',
            'position' => 'required|string|exists:positions,title',
            'department_id'      => 'sometimes|exists:departments,id',
            'effective_date'     => 'required|date',
            'remarks'            => 'nullable|string',
        ]);

        $employee = Employee::findOrFail($id);

        if ($employee->employment_status !== 'resigned') {
            return response()->json(['message' => 'Employee is not currently marked as resigned'], 422);
        }

        DB::transaction(function () use ($employee, $request, $user) {
            EmploymentHistory::create([
                'employee_id'                 => $employee->id,
                'previous_position'           => $employee->position,
                'new_position'                => $request->position,
                'previous_employment_status'  => 'resigned',
                'new_employment_status'       => $request->employment_status,
                'effective_date'              => $request->effective_date,
                'remarks'                     => $request->remarks ?? 'Rehired',
            ]);

            $employee->update([
                'employment_status' => $request->employment_status,
                'position'           => $request->position,
                'department_id'      => $request->department_id ?? $employee->department_id,
                'is_active'          => true,
            ]);

            // Re-initialize leave credits for the current year, same as a fresh hire
            $creditController = new LeaveCreditController();
            $creditController->initializeSingleEmployeeCredits($employee->id, now()->year);

            ActivityLog::create([
                'user_id'      => $user->id,
                'action'       => 'employee.rehired',
                'description'  => "Rehired {$employee->first_name} {$employee->surname} as {$request->position} effective {$request->effective_date}",
                'subject_type' => 'Employee',
                'subject_id'   => $employee->id,
            ]);
        });

        return response()->json(['message' => 'Employee rehired successfully']);
    }
    public function destroy(string $id)
    {
        // OPTIMIZED: check auth first before any DB query
        $user = request()->user();
        // if (!$user || $user->role !== 'super_admin') {
        //     return response()->json(['message' => 'Forbidden'], 403);
        // }
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = Employee::find($id);
        // Single query: find and update in one go
        $affected = Employee::where('id', $id)->update(['is_active' => false]);

        if (!$affected) {
            return response()->json(['message' => 'Employee not found'], 404);
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'action'       => 'employee.deactivated',
            'description'  => "Deactivated employee {$employee->first_name} {$employee->surname}",
            'subject_type' => 'Employee',
            'subject_id'   => $id,

        ]);

        return response()->json(['message' => 'Employee deactivated successfully']);
    }
}
