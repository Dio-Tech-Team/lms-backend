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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use App\Models\ActivityLog;
use Carbon\Carbon;
use App\Models\LeaveAccrual;
use App\Service\LeaveAccrualService;


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

        // Get IDs of employees currently on approved leave — single query, not per-row
        $onLeaveIds = LeaveApplication::where('status', 'approved')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->pluck('employee_id')
            ->toArray();


        if ($request->filled('on_leave')) {
            $request->boolean('on_leave')
                ? $query->whereIn('employees.id', $onLeaveIds)
                : $query->whereNotIn('employees.id', $onLeaveIds);
        }
        $employees = $query
            ->orderByDesc('employees.is_active')
            ->orderBy('employees.surname')
            ->orderBy('employees.first_name')
            ->paginate(10);

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
            'birthdate'                         => 'nullable|date|before:today',
            'place_of_birth'                   => 'nullable|string',
            'sex'                              => 'required|in:male,female',
            'civil_status'                     => 'nullable|in:single,married,widowed,separated',
            'height'                           => 'nullable|string',
            'weight'                           => 'nullable|string',
            'bloodtype'                        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'highest_educational_attainment'   => 'nullable|in:elementary,secondary,vocational,college,graduate',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|digits:11',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'employment_status'                => 'required|in:permanent,casual,elected,job_order,resigned',
            // 'position'                         => 'required|string',
            'position' => 'required|string|exists:positions,title',
            'department_id'                    => 'required|exists:departments,id',
            'date_hired' => 'required|date|before_or_equal:today',
            'schedule_type'                    => 'nullable|in:4day,5day',
        ]);

        // Authorization check before DB transaction
        $admin = request()->user();
        if (!$admin || !in_array($admin->role, ['super_admin', 'hr_admin'], true)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $employee = DB::transaction(function () use ($request) {
            $defaultPassword = $request->id_number;

            $user = User::create([
                'username' => $request->username,
                'email' => $request->email,
                'password' => Hash::make($defaultPassword),
                'role' => 'employee',
                'must_change_password' => true,
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
                'schedule_type'                      => $request->schedule_type ?? '4day',
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
            'employees.schedule_type',
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
            'schedule_type'                  => $employee->schedule_type,
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
            'id_number'                        => 'sometimes|string|unique:employees,id_number,' . $employee->id,
            'birthdate'                         => 'nullable|date|before:today',
            'place_of_birth'                   => 'nullable|string',
            'sex'                              => 'sometimes|in:male,female',
            'civil_status'                     => 'sometimes|nullable|in:single,married,widowed,separated',
            'height'                           => 'nullable|string',
            'weight'                           => 'nullable|string',
            'bloodtype'                        => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'highest_educational_attainment'   => 'sometimes|nullable|in:elementary,secondary,vocational,college,graduate',
            'residential_address'              => 'nullable|string',
            'contact_number'                   => 'nullable|digits:11',
            'umid_id'                          => 'nullable|string',
            'pagibig_id'                       => 'nullable|string',
            'philhealth_number'                => 'nullable|string',
            'psn_number'                       => 'nullable|string',
            'tin_number'                       => 'nullable|string',
            'department_id'                    => 'sometimes|exists:departments,id',
            'position' => 'sometimes|string|exists:positions,title',
            'date_hired' => 'sometimes|date|before_or_equal:today',
            'schedule_type'                    => 'sometimes|in:4day,5day',
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

    public function updateOwnProfile(Request $request)
    {
        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'message' => 'No employee record is linked to your account.'
            ], 404);
        }

        $validated = $request->validate([
            'birthdate'                      => 'nullable|date|before:today',
            'place_of_birth'                 => 'nullable|string|max:255',
            'residential_address'            => 'nullable|string|max:500',
            'contact_number'                 => 'nullable|digits:11',
            'sex'                            => 'nullable|in:male,female',
            'civil_status'                   => 'nullable|in:single,married,widowed,separated',
            'height'                         => 'nullable|numeric|min:50|max:250',
            'weight'                         => 'nullable|numeric|min:20|max:400',
            'bloodtype'                      => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'highest_educational_attainment' => 'nullable|in:elementary,secondary,vocational,college,graduate',
            'tin_number'                     => 'nullable|digits_between:9,12',
            'umid_id'                        => 'nullable|digits:12',
            'pagibig_id'                     => 'nullable|digits:12',
            'philhealth_number'              => 'nullable|digits:12',
            'psn_number'                     => 'nullable|digits:16',
        ]);

        // Only fields the request actually sent — a partial save must not
        // blank out everything else on the record.
        $employee->update($validated);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'employee.self_updated',
            'description'  => "{$employee->first_name} {$employee->surname} updated their own profile ("
                . implode(', ', array_keys($validated)) . ')',
            'subject_type' => 'Employee',
            'subject_id'   => $employee->id,
        ]);

        return response()->json([
            'message'  => 'Profile updated successfully',
            'employee' => $employee->fresh(),
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

        $yearsServed = max(0, (int) floor($startDate->diffInYears(now())));

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

        app(LeaveAccrualService::class)->accrueForEmployee(Employee::find($employee->id));

        $year = (int) $request->input('year', now()->year);   // NEW

        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
        $slConfig = LeaveConfiguration::where('code', 'SL')->first();
        $flConfig = LeaveConfiguration::where('code', 'FL')->first();

        return response()->json([
            'employee' => [
                'id'       => $employee->id,
                'name'     => $employee->first_name . ' ' . $employee->surname,
                'position' => $employee->position,
            ],
            'year' => $year,
            'vacation_leave' => $this->buildLeaveCardForType(
                $employee,
                $vlConfig,
                'vl',
                $year,
                $flConfig ? [$flConfig->id] : []
            ),
            'sick_leave' => $this->buildLeaveCardForType($employee, $slConfig, 'sl', $year),
        ]);
    }
    public function leaveCardPdf(string $id, Request $request)
    {
        $employee = Employee::select('id', 'user_id', 'first_name', 'surname', 'position', 'date_hired', 'employment_status')
            ->findOrFail($id);

        $user = $request->user();
        if (!in_array($user->role, ['hr_admin', 'super_admin'], true) && $employee->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        app(LeaveAccrualService::class)->accrueForEmployee(Employee::find($employee->id));

        $year = (int) $request->input('year', now()->year);

        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();
        $slConfig = LeaveConfiguration::where('code', 'SL')->first();
        $flConfig = LeaveConfiguration::where('code', 'FL')->first();

        $data = [
            'employee' => [
                'name'               => $employee->first_name . ' ' . $employee->surname,
                'position'           => $employee->position,
                'date_hired'         => $employee->date_hired,
                'employment_status'  => $employee->employment_status,
            ],
            'year'           => $year,
            'vacation_leave' => $this->buildLeaveCardForType($employee, $vlConfig, 'vl', $year, $flConfig ? [$flConfig->id] : []),
            'sick_leave'     => $this->buildLeaveCardForType($employee, $slConfig, 'sl', $year),
        ];

        $pdf = Pdf::loadView('pdf.leave-card', $data);
        return $pdf->stream("leave-card-{$employee->surname}-{$year}.pdf");
    }

    /**
     * Plain-language reason for a monthly credit row, so the employee can
     * see why they earned less than 1.250 or lost VL to tardiness.
     */
    /**
     * Plain-language description of a DTR upload row: LWOP days (and the
     * reduced earning for casual employees) and tardiness for VL.
     */
    private function monthlyParticulars($row, string $type): string
    {
        $num = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        $parts = [];

        $lwop = (float) $row->absent_without_leave_days;
        if ($lwop > 0) {
            $earned = (float) ($type === 'vl' ? $row->vl_earned : $row->sl_earned);
            $parts[] = $num($lwop) . ' day(s) absent w/o leave'
                . ($earned < LeaveAccrualService::MONTHLY ? ' (earned ' . number_format($earned, 3) . ')' : '');
        }

        // Tardiness only affects VL
        if ($type === 'vl') {
            $mins = (int) $row->late_am_minutes + (int) $row->late_pm_minutes
                + (int) $row->undertime_am_minutes + (int) $row->undertime_pm_minutes;

            if ($mins > 0) {
                $h = intdiv($mins, 60);
                $m = $mins % 60;
                $time = trim(($h ? "{$h}h " : '') . ($m ? "{$m}m" : ''));
                $parts[] = "{$time} late/UT (−" . $num($row->tardiness_equivalent_days) . ')';
            }
        }

        $label = "{$row->month} {$row->year} DTR";
        return $parts ? $label . ': ' . implode('; ', $parts) : $label;
    }

    /**
     * A cancelled approved leave as two card rows: the original deduction
     * on the leave dates, then the restoration on the cancellation date.
     * $rec is what remains after a partial cancel, or null after a full one.
     */
    private function cancelledLeaveRows(LeaveApplication $app, ?LeaveRecord $rec): array
    {
        $num = fn($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');

        $origDays    = (float) ($app->original_days_applied ?? $app->days_applied);
        $origEnd     = Carbon::parse($app->original_end_date ?? $app->end_date);
        $keptLwop    = (float) ($rec->no_pay_days ?? 0);
        $keptCredits = $rec
            ? ((float) $rec->days_taken - $keptLwop) * (float) ($rec->credit_factor ?? 1)
            : 0;

        $origLwop    = $keptLwop + (float) $app->lwop_removed;
        $origCredits = $keptCredits + (float) $app->credits_returned;

        $fullCancel   = $app->status === 'cancelled';
        $daysReturned = $fullCancel ? $origDays : $origDays - (float) $app->days_applied;

        $name      = $app->leaveConfiguration->name;
        $start     = Carbon::parse($app->start_date);
        $cancelled = Carbon::parse($app->cancelled_at)->timezone('Asia/Manila');
        return [
            [
                'sort_date'   => $start,
                'period'      => $start->format('M j, Y') . ' to ' . $origEnd->format('M j, Y'),
                'particulars' => "{$name} taken",
                'earned'      => 0,
                'abs_wp'      => round($origDays - $origLwop, 3),
                'abs_wop'     => round($origLwop, 3),
                'used'        => round($origCredits, 3),
            ],
            [
                // Cancelled before the leave started: keep it right after the
                // deduction so the running balance doesn't jump up first
                'sort_date'   => $cancelled->lt($start) ? $start->copy() : $cancelled,
                'period'      => $cancelled->format('M j, Y'),
                'particulars' => ($fullCancel ? 'Cancelled' : 'Partially cancelled')
                    . " {$name}: {$num($daysReturned)} day(s) returned"
                    . ((float) $app->lwop_removed > 0 ? " ({$num($app->lwop_removed)} LWOP removed)" : ''),
                'earned'      => round((float) $app->credits_returned, 3),
                'abs_wp'      => 0,
                'abs_wop'     => 0,
                'used'        => 0,
            ],
        ];
    }
    private function buildLeaveCardForType(Employee $employee, ?LeaveConfiguration $config, string $type, int $year, array $deductionConfigIds = []): array
    {
        $entries = [];

        $credit = null;
        if ($config) {
            $credit = \App\Models\LeaveCredit::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id)
                ->where('year', $year)
                ->first();
        }

        $attendanceRows = Attendance::where('employee_id', $employee->id)->where('year', $year)->get();

        // Accruals on or before the paper-card transfer are already in the opening balance
        $openingDate = $config
            ? \App\Models\LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $config->id)
            ->whereNotNull('opening_balance_date')
            ->min('opening_balance_date')
            : null;

        // Monthly 1.250 credit posted on the hiring-date anniversary
        $accruals = LeaveAccrual::where('employee_id', $employee->id)
            ->whereYear('accrual_date', $year)
            ->when($openingDate, fn($q) => $q->whereDate('accrual_date', '>', $openingDate))
            ->get();

        foreach ($accruals as $acc) {
            $date = Carbon::parse($acc->accrual_date);

            $entries[] = [
                'sort_date'   => $date,
                'period'      => $date->format('M j, Y'),
                'particulars' => 'Monthly credit',
                'earned'      => round((float) ($type === 'vl' ? $acc->vl_earned : $acc->sl_earned), 3),
                'abs_wp'      => 0,
                'abs_wop'     => 0,
                'used'        => 0,
            ];
        }

        // DTR upload: casual-LWOP reduction (negative earned) and, for VL, tardiness
        foreach ($attendanceRows as $row) {
            $reduction = round((float) ($type === 'vl' ? $row->vl_earned : $row->sl_earned) - LeaveAccrualService::MONTHLY, 3);
            $tardiness = $type === 'vl'
                ? round((float) $row->tardiness_equivalent_days - (float) $row->lwop_days, 3)
                : 0;

            // Nothing to show for this leave type
            if (
                $reduction == 0 && $tardiness == 0
                && (float) $row->absent_without_leave_days == 0
                && (float) $row->absent_with_leave_days == 0
            ) {
                continue;
            }

            $uploaded = Carbon::parse($row->created_at);

            $entries[] = [
                'sort_date'   => $uploaded,
                'period'      => $uploaded->format('M j, Y'),
                'particulars' => $this->monthlyParticulars($row, $type),
                'earned'      => $reduction,
                'abs_wp'      => (float) $row->absent_with_leave_days,
                'abs_wop'     => (float) $row->absent_without_leave_days,
                'used'        => $tardiness,
            ];
        }
        if ($config) {
            $recordConfigIds = array_merge([$config->id], $deductionConfigIds);
            $leaveRecords = LeaveRecord::where('employee_id', $employee->id)
                ->whereIn('leave_configuration_id', $recordConfigIds)
                ->whereYear('start_date', $year)
                ->with('leaveConfiguration:id,name')
                ->get();
            // Approved leaves later cancelled (fully or partly) through cancelApproved()
            $cancelledApps = LeaveApplication::where('employee_id', $employee->id)
                ->whereIn('leave_configuration_id', $recordConfigIds)
                ->whereNotNull('cancelled_at')
                ->whereNotNull('credits_returned')
                ->whereYear('start_date', $year)
                ->with('leaveConfiguration:id,name')
                ->get()
                ->keyBy('id');
            $handled = [];

            foreach ($leaveRecords as $rec) {
                $app = $rec->leave_application_id ? $cancelledApps->get($rec->leave_application_id) : null;
                if ($app) {
                    array_push($entries, ...$this->cancelledLeaveRows($app, $rec));
                    $handled[] = $app->id;
                    continue;
                }
                $start   = \Carbon\Carbon::parse($rec->start_date);
                $end     = \Carbon\Carbon::parse($rec->end_date);
                $withPay = (float) $rec->days_taken - (float) $rec->no_pay_days;

                $entries[] = [
                    'sort_date'   => $start,
                    'period'      => $start->format('M j, Y') . ' to ' . $end->format('M j, Y'),
                    // Tardiness that exceeded the VL balance is also stored as a
                    // leave record. Label it, or it reads like leave the employee filed.
                    'particulars' => $rec->attendance_id
                        ? 'Tardiness exceeded VL balance (LWOP)'
                        : ($rec->slip_id
                            ? 'Personal slip exceeded VL balance (LWOP)'
                            : $rec->leaveConfiguration->name . ' taken'),
                    'earned'      => 0,
                    'abs_wp'      => round($withPay, 3),
                    'abs_wop'     => round((float) $rec->no_pay_days, 3),
                    // W/P shows days; USED shows credits actually deducted
                    'used'        => round($withPay * (float) ($rec->credit_factor ?? 1), 3),
                ];
            }

            // Fully cancelled leaves have no record left
            foreach ($cancelledApps as $app) {
                if ($app->status === 'cancelled' && !in_array($app->id, $handled, true)) {
                    array_push($entries, ...$this->cancelledLeaveRows($app, null));
                }
            }
            // Personal slips deduct VL only
            if ($type === 'vl') {
                $slips = \App\Models\Slip::where('employee_id', $employee->id)
                    ->where('status', 'active')
                    ->whereYear('date', $year)
                    ->get();

                foreach ($slips as $slip) {
                    $h = intdiv($slip->minutes, 60);
                    $m = $slip->minutes % 60;
                    $time = trim(($h ? "{$h}h " : '') . ($m ? "{$m}m" : ''));

                    $entries[] = [
                        'sort_date'   => $slip->date,
                        'period'      => $slip->date->format('M j, Y'),
                        'particulars' => "Personal slip ({$time})",
                        'earned'      => 0,
                        'abs_wp'      => 0,
                        'abs_wop'     => 0,
                        // Only what VL absorbed; any excess shows as its own LWOP row
                        'used'        => round($slip->equivalent_days - $slip->lwop_days, 3),
                    ];
                }
            }
            $monetizations = \App\Models\LeaveMonetization::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $config->id)
                ->where('status', 'approved')
                ->whereYear('applied_at', $year)
                ->get();

            foreach ($monetizations as $mon) {
                $date = \Carbon\Carbon::parse($mon->reviewed_at ?? $mon->applied_at);

                $entries[] = [
                    'sort_date'   => $date,
                    'period'      => $date->format('M j, Y'),
                    'particulars' => $config->name . ' monetized',
                    'earned'      => 0,
                    'abs_wp'      => 0,
                    'abs_wop'     => 0,
                    'used'        => round((float) ($mon->approved_days ?? $mon->days_monetized), 3),
                ];
            }
        }
        if ($config && $credit) {
            $baseDate = \Carbon\Carbon::create($year, 1, 1)->subDay();
            if ((float) $credit->opening_balance > 0) {
                $earliestCreditYear = \App\Models\LeaveCredit::where('employee_id', $employee->id)
                    ->where('leave_configuration_id', $config->id)
                    ->where('opening_balance', '>', 0)
                    ->min('year');
                if ($year === $earliestCreditYear) {

                    // Actual transfer date; older data falls back to Dec 31 of the prior year
                    $transferDate = $credit->opening_balance_date
                        ? \Carbon\Carbon::parse($credit->opening_balance_date)
                        : $baseDate;
                    // Paper balance is as of the day it was transferred
                    $asOfDate = $transferDate;


                    $entries[] = [
                        'sort_date'   => $asOfDate,
                        // 'period'      => $transferDate->format('M j, Y'),
                        'period'      => $asOfDate->format('M j, Y'),
                        'particulars' => 'Transferred from physical leave card',
                        'earned'      => round((float) $credit->opening_balance, 3),
                        'abs_wp'      => 0,
                        'abs_wop'     => 0,
                        'used'        => 0,
                    ];


                    // Leave filed after the transfer but dated before it (e.g. past
                    // sick days) came out of the transferred balance, so list it
                    // after the transfer row instead of above it
                    foreach ($entries as &$e) {
                        if (
                            $e['particulars'] !== 'Transferred from physical leave card'
                            && $e['sort_date']->lt($asOfDate)
                        ) {
                            $e['sort_date'] = $asOfDate->copy()->addSecond();
                        }
                    }
                    unset($e);
                    // Anniversary credits after the transfer, plus DTR reductions (casual LWOP)
                    $accrualSum   = $accruals->sum(fn($a) => (float) ($type === 'vl' ? $a->vl_earned : $a->sl_earned));
                    $reductionSum = $attendanceRows->sum(fn($r) => (float) ($type === 'vl' ? $r->vl_earned : $r->sl_earned) - LeaveAccrualService::MONTHLY);
                    $expectedTotalCredits = (float) $credit->opening_balance + $accrualSum + $reductionSum;
                    $delta = round((float) $credit->total_credits - $expectedTotalCredits, 3);
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
                            'period'      => $correctionDate->format('M j, Y'),
                            'particulars' => "Balance correction"
                                . ($delta > 0 ? ' (increase)' : ' (decrease)')
                                . " by {$actor}",
                            'earned'      => $delta > 0 ? $delta : 0,
                            'abs_wp'      => 0,
                            'abs_wop'     => 0,
                            'used'        => $delta < 0 ? abs($delta) : 0,
                        ];
                    }
                }
            }
        }

        usort($entries, fn($a, $b) => $a['sort_date'] <=> $b['sort_date']);

        $balance = 0;
        foreach ($entries as &$entry) {
            $balance += $entry['earned'] - $entry['used'];
            $entry['balance'] = round($balance, 3);
            // Kept for the PDF, which merges the VL and SL lists in this order
            $entry['sort_key'] = Carbon::parse($entry['sort_date'])->format('Y-m-d H:i:s');
            unset($entry['sort_date']);
        }

        return $entries;
    }

    /**
     * Switch active employees to a work schedule in one go. dry_run returns
     * who would change, so HR can untick employees who keep their schedule
     * (some departments mix 4-day and 5-day staff). Approved leaves keep
     * their stored credit_factor; pending ones are recounted on approval.
     */
    public function bulkSchedule(Request $request)
    {
        $validated = $request->validate([
            'schedule_type'  => 'required|in:4day,5day',
            'department_id'  => 'nullable|exists:departments,id',
            'employee_ids'   => 'nullable|array',
            'employee_ids.*' => 'integer|exists:employees,id',
            'dry_run'        => 'nullable|boolean',
        ]);

        // Active employees not yet on the chosen schedule
        $query = Employee::where('is_active', true)
            ->where(fn($q) => $q->where('schedule_type', '!=', $validated['schedule_type'])
                ->orWhereNull('schedule_type'))
            ->when($validated['department_id'] ?? null, fn($q, $d) => $q->where('department_id', $d));

        if ($request->boolean('dry_run')) {
            $employees = $query->with('department:id,name')
                ->orderBy('surname')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'surname', 'department_id'])
                ->map(fn($e) => [
                    'id'         => $e->id,
                    'name'       => "{$e->surname}, {$e->first_name}",
                    'department' => $e->department?->name,
                ]);

            return response()->json(['employees' => $employees]);
        }

        // Only the employees HR left ticked
        if ($request->has('employee_ids')) {
            $query->whereIn('id', $validated['employee_ids'] ?? []);
        }

        $count = $query->update(['schedule_type' => $validated['schedule_type']]);

        $label = $validated['schedule_type'] === '5day' ? '5-day (Mon–Fri)' : '4-day (Mon–Thu)';

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'employee.schedule_bulk_updated',
            'description'  => "Changed work schedule to {$label} for {$count} employee(s)",
            'subject_type' => 'Employee',
            'subject_id'   => null,
        ]);

        return response()->json([
            'message' => "{$count} employee(s) switched to the {$label} schedule.",
            'updated' => $count,
        ]);
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
                    'sex'            => $employee->sex,   // ADD THIS
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
