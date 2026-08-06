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

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('employees.first_name', 'LIKE', "%{$search}%")
                    ->orWhere('employees.surname', 'LIKE', "%{$search}%")
                    ->orWhere('employees.id_number', 'LIKE', "%{$search}%");
            });
        }
        // Apply pagination
        $employees = $query->paginate(10);

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
            'employment_status'                => 'required|in:permanent,casual,elected,job_order,resigned',
            'position'                         => 'required|string',
            'department_id'                    => 'required|exists:departments,id',
            'date_hired'                       => 'required|date',
        ]);

        // Authorization check before DB transaction
        $admin = request()->user();
        if (!$admin || $admin->role !== 'super_admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = DB::transaction(function () use ($request) {
            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'employee',
            ]);

            // event(new Registered($user));


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
            'department'                     => $employee->department_name,
            'date_hired'                     => $employee->date_hired,
            'employment_history'             => $employee->employment_history,
            'step_increment'                 => $stepIncrementInfo,
            'loyalty_pay'                    => $loyaltyPayInfo,
            'retirement'                     => $retirementInfo,
            'is_active'                      => $employee->is_active,
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
            // 'employment_status'                => 'sometimes|in:permanent,casual,elected,job_order',
            'date_hired'                       => 'sometimes|date',
        ]);

        // $employee = Employee::findOrFail($id);
        $employee->update($validated);


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
    // private function calculateLoyaltyPay($employee)
    // {
    //     if ($employee->employment_status !== 'permanent') {
    //         return [
    //             'eligible'          => false,
    //             'message'           => 'Not applicable - employee is not currently permanent',
    //             'years_served'      => 0,
    //             'years_until_next'  => null,
    //             'next_milestone'    => 10,
    //         ];
    //     }

    //     $employmentHistory = $employee->relationLoaded('employment_history')
    //         ? $employee->employment_history
    //         : $employee->employment_history()->orderBy('effective_date', 'desc')->get();

    //     $earliestPermanent = $employmentHistory
    //         ->filter(fn($history) => $history->new_employment_status === 'permanent')
    //         ->sortBy('effective_date')
    //         ->first();

    //     $startDate = $earliestPermanent
    //         ? \Carbon\Carbon::parse($earliestPermanent->effective_date)
    //         : \Carbon\Carbon::parse($employee->date_hired);

    //     $yearsServed = max(0, $startDate->diffInYears(now()));

    //     if ($yearsServed < 10) {
    //         $yearsUntilFirst = 10 - $yearsServed;
    //         return [
    //             'eligible'          => false,
    //             'since'             => $startDate->format('Y-m-d'),
    //             'years_served'      => $yearsServed,
    //             'milestones_received'   => 0, //new added 
    //             'years_until_next'  => $yearsUntilFirst,
    //             'next_milestone'    => 10,
    //         ];
    //     }
    //     $yearsAfterFirst = $yearsServed - 10;
    //     $milestonesPassed = floor($yearsAfterFirst / 5) + 1;
    //     $nextMilestone = 10 + ($milestonesPassed * 5);
    //     $yearsUntilNext = $nextMilestone - $yearsServed;

    //     return [
    //         'eligible'              => true,
    //         'since'                 => $startDate->format('Y-m-d'),
    //         'years_served'          => $yearsServed,
    //         'milestones_received'   => (int) $milestonesPassed,
    //         'years_until_next'      => $yearsUntilNext,
    //         'next_milestone'        => $nextMilestone,
    //     ];
    // }

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

    private function buildLeaveCardForType(Employee $employee, ?LeaveConfiguration $config, string $type): array
    {
        $entries = [];

        $attendanceRows = \App\Models\Attendance::where('employee_id', $employee->id)->get();

        foreach ($attendanceRows as $row) {
            $earned = $type === 'vl'
                ? $row->vl_earned - $row->tardiness_equivalent_days
                : $row->sl_earned;

            $entries[] = [
                'sort_date'   => \Carbon\Carbon::parse("{$row->month} 1, {$row->year}"),
                'period'      => "{$row->month} {$row->year}",
                'particulars' => 'Monthly credit',
                'earned'      => round($earned, 3),
                'abs_wp'      => (float) $row->absent_with_leave_days,
                'abs_wop'     => (float) $row->absent_without_leave_days,
                'used'        => 0,
            ];
        }

        // USED entries — approved leave actually taken for this leave type
        if ($config) {
            $leaveRecords = \App\Models\LeaveRecord::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id) // ← verify this column name matches your schema
                ->get();

            foreach ($leaveRecords as $rec) {
                $start = \Carbon\Carbon::parse($rec->start_date);
                $end   = \Carbon\Carbon::parse($rec->end_date);

                $entries[] = [
                    'sort_date'   => $start,
                    'period'      => $start->format('m-d-y') . ' to ' . $end->format('m-d-y'),
                    'particulars' => $config->name . ' taken',
                    'earned'      => 0,
                    'abs_wp'      => 0,
                    'abs_wop'     => 0,
                    'used'        => round((float) $rec->days_taken, 3),
                ];
            }
        }

        // Sort chronologically, then compute running balance
        usort($entries, fn($a, $b) => $a['sort_date'] <=> $b['sort_date']);

        $balance = 0;
        foreach ($entries as &$entry) {
            $balance += $entry['earned'] - $entry['used'];
            $entry['balance'] = round($balance, 3);
            unset($entry['sort_date']); // internal only, not needed in the response
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

        //     $totalYearsAtForecast = $forecastYear - $baseDate->year;

        //     if ($totalYearsAtForecast > 0 && $totalYearsAtForecast % 3 === 0) {
        //         $currentStep = (int)(($totalYearsAtForecast - 3) / 3) + 1;
        //         $nextStep = $currentStep + 1;

        //         $currentStepDisplay = min(max($currentStep, 1), 7);
        //         $nextStepDisplay = min($nextStep, 8);
        //         $nextStepDate = Carbon::create(
        //             $forecastYear,
        //             $baseDate->month,
        //             $baseDate->day
        //         )->format('Y-m-d');

        //         $forecastRecords[] = [
        //             'employee_id'    => $employee->id,
        //             'name'           => "{$employee->first_name} {$employee->surname}",
        //             'position'       => $employee->position,
        //             'department'     => $employee->department?->name ?? 'Unassigned',
        //             'current_step'   => $currentStepDisplay,
        //             'next_step'      => $nextStepDisplay,
        //             'next_step_date' => $nextStepDate,
        //         ];
        //     }
        // }

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

    public function rehire(Request $request, string $id)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['hr_admin', 'super_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'employment_status' => 'required|in:permanent,casual,elected,job_order',
            'position'           => 'required|string',
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
