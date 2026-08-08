<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveApplication;
use App\Models\Employee;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use App\Models\LeaveRecord;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use App\Models\ActivityLog;

class LeaveApplicationController extends Controller
{

    private function isAdmin($user): bool
    {
        return $user && in_array($user->role, ['hr_admin', 'super_admin'], true);
    }
    public function index(Request $request)
    {
        $user = $request->user();
        // OPTIMIZED: INNER JOIN + vertical partitioning + pagination + status index used
        $applications = LeaveApplication::select([
            'leave_applications.id',
            'leave_applications.employee_id',
            'leave_applications.leave_configuration_id',
            'leave_applications.start_date',
            'leave_applications.end_date',
            'leave_applications.days_applied',
            'leave_applications.reason',
            'leave_applications.status',
            'leave_applications.applied_at',
            'leave_applications.reviewed_at',
            'leave_applications.reviewed_by',
            'employees.first_name',
            'employees.surname',
            'departments.name as department_name',
            'leave_configurations.name as leave_type_name',
            'leave_configurations.code as leave_type_code',
            'users.username as reviewed_by_username',
            'leave_credits.remaining_balance',
        ])
            ->join('employees', 'leave_applications.employee_id', '=', 'employees.id')
            ->join('departments', 'employees.department_id', '=', 'departments.id')
            ->join('leave_configurations', 'leave_applications.leave_configuration_id', '=', 'leave_configurations.id')
            ->leftJoin('users', 'leave_applications.reviewed_by', '=', 'users.id') // LEFT JOIN since reviewer may be null
            ->leftJoin('leave_credits', function ($join) {
                $join->on('leave_credits.employee_id', '=', 'leave_applications.employee_id')
                    ->on('leave_credits.leave_configuration_id', '=', 'leave_applications.leave_configuration_id')
                    ->whereRaw('leave_credits.year = YEAR(leave_applications.applied_at)');
            })
            // RESTRICT REGULAR EMPLOYEES TO THEIR OWN APPLICATIONS
            ->when(!$this->isAdmin($user), function ($query) use ($user) {
                $query->where('leave_applications.employee_id', $user->employee?->id);
            })
            ->when($request->status, function ($query) use ($request) {
                $query->where('leave_applications.status', $request->status); // uses status index!
            })
            ->when($request->department_id, function ($query) use ($request) {
                $query->where('employees.department_id', $request->department_id);
            })
            ->when($request->year, function ($query) use ($request) {
                $query->whereYear('leave_applications.applied_at', $request->year);
            })
            ->when($request->search, function ($query) use ($request) {
                $query->where(function ($q) use ($request) {
                    $q->where('employees.first_name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('employees.surname', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->orderBy('leave_applications.created_at', 'desc')
            ->paginate(15);

        return response()->json($applications);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'            => 'nullable|exists:employees,id',
            'leave_configuration_id' => 'required|exists:leave_configurations,id',
            'start_date'             => 'required|date',
            'end_date'               => 'required|date|after_or_equal:start_date',
            'days_applied'           => 'required|numeric|min:0.5',
            'reason'                 => 'nullable|string',
            'is_paper_submission'    => 'nullable|boolean',
        ]);

        $user = $request->user();
        $config = LeaveConfiguration::findOrFail($request->leave_configuration_id);

        if (!$config->is_active) {
            return response()->json([
                'message' => 'This leave type is no longer active and cannot be used for new applications.'
            ], 422);
        }
        if (($request->filled('employee_id') || $request->boolean('is_paper_submission'))
            && !$this->isAdmin($request->user())
        ) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->has('employee_id') && $request->filled('employee_id')) {
            $employee = Employee::find($validated['employee_id']);
        } else {
            $employee = $request->user()->employee;
        }

        if (!$employee) {
            return response()->json([
                'message' => 'Target employee profile could not be determined.'
            ], 422);
        }

        if ($employee->employment_status === 'job_order' && $config->code !== 'WL') {
            return response()->json([
                'message' => 'Unauthorized: Job Order personnel are only eligible for Wellness Leave.'
            ], 403);
        }

        // Only block duplicates if it's NOT a paper submission
        if (!$request->boolean('is_paper_submission')) {
            $existing = LeaveApplication::where('employee_id', $employee->id)
                ->where('status', 'pending')
                ->where('start_date', $validated['start_date'])
                ->where('end_date', $validated['end_date'])
                ->exists();

            if ($existing) {
                return response()->json(['message' => 'You already have a pending application for these dates.'], 422);
            }
        }
        $year = Carbon::parse($validated['start_date'])->year;

        // Fetch credit once
        $credit = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $config->id)
            ->where('year', $year)
            ->first();

        if (!$credit) {
            return response()->json([
                'message' => 'No leave credits found for the year ' . $year . '. Please contact HR to initialize credits.'
            ], 422);
        }

        // Calculate balance status once
        $hasInsufficientBalance = $credit ? ($credit->remaining_balance < $validated['days_applied']) : false;
        $eligibilityError = $this->validateLeaveEligibility($config, $employee);
        if ($eligibilityError) {
            return response()->json(['message' => $eligibilityError], 422);
        }

        // 1. Validation Logic
        if (in_array($config->code, ['WL', 'SPL'])) {
            if ($hasInsufficientBalance) {
                return response()->json([
                    'message' => 'Insufficient balance for ' . $config->name . '. You cannot file this leave.'
                ], 422);
            }
        }
        if ($config->code === 'FL') {
            $vlCredit = LeaveCredit::whereHas('leaveConfiguration', function ($query) {
                $query->where('code', 'VL');
            })
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->first();

            if (!$vlCredit || $vlCredit->remaining_balance < $validated['days_applied']) {
                return response()->json([
                    'message' => 'Insufficient Vacation Leave balance to cover this Forced Leave application.'
                ], 422);
            }
        }

        if ($config->code === 'WL' && $validated['days_applied'] > 3) {
            return response()->json([
                'message' => 'Wellness leave cannot exceed 3 consecutive days per application.'
            ], 422);
        }

        // Add this line before the paper-submission branch
        $targetCode = ($config->code === 'FL') ? 'VL' : $config->code;
        $deductionCredit = ($targetCode === $config->code)
            ? $credit
            : LeaveCredit::where('employee_id', $employee->id)
            ->whereHas('leaveConfiguration', fn($q) => $q->where('code', $targetCode))
            ->where('year', $year)
            ->first();

        // 2. Choose Workflow Route
        if ($request->boolean('is_paper_submission')) {
            $application = DB::transaction(function () use ($employee, $validated,  $deductionCredit, $request, $config) {
                $app = LeaveApplication::create([
                    'employee_id'            => $employee->id,
                    'leave_configuration_id' => $validated['leave_configuration_id'],
                    'start_date'             => $validated['start_date'],
                    'end_date'               => $validated['end_date'],
                    'days_applied'           => $validated['days_applied'],
                    'reason'                 => ($validated['reason'] ?? 'No reason provided') . ' (Filed via Paper Form)',
                    'status'                 => 'approved',
                    'applied_at'             => $request->input('applied_at', now()),
                    'reviewed_at'            => now(),
                    'reviewed_by'            => $request->user()->id,
                    'filed_by'               => $request->user()->id,
                ]);
                $noPayDays = $deductionCredit ? $deductionCredit->deductLeave((float) $validated['days_applied']) : (float) $validated['days_applied'];

                LeaveRecord::create([
                    'employee_id'            => $employee->id,
                    'leave_configuration_id' => $validated['leave_configuration_id'],
                    'recorded_by'            => $request->user()->id,
                    'start_date'             => $validated['start_date'],
                    'end_date'               => $validated['end_date'],
                    'days_taken'             => $validated['days_applied'],
                    'no_pay_days'            => $noPayDays,
                    'remarks'                => ($noPayDays > 0 ? "{$noPayDays} day(s) LWOP. " : '') . 'Paper Submission Backup ID: ' . $app->id,
                ]);

                return $app;
            });
        } else {
            $application = LeaveApplication::create([
                'employee_id'            => $employee->id,
                'leave_configuration_id' => $validated['leave_configuration_id'],
                'start_date'             => $validated['start_date'],
                'end_date'               => $validated['end_date'],
                'days_applied'           => $validated['days_applied'],
                'reason'                 => $validated['reason'],
                'status'                 => 'pending',
                'applied_at'             => now(),
            ]);
        }

        return response()->json([
            'message'              => 'Leave application processed successfully',
            'data'                 => $application,
            'insufficient_balance' => $hasInsufficientBalance,
            'remaining_balance'    => $credit->remaining_balance ?? 0,
        ], 201);
    }
    public function approve(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);
        $config = LeaveConfiguration::find($application->leave_configuration_id);

        if ($application->status !== 'pending') {
            return response()->json(['message' => 'Application is already ' . $application->status], 400);
        }

        // If it's Force Leave, we need the VL credit record, not the FL record.
        $targetCode = ($config->code === 'FL') ? 'VL' : $config->code;

        $year = Carbon::parse($application->start_date)->year;

        $result = DB::transaction(function () use ($application, $request, $config, $targetCode, $year) {
            $credit = LeaveCredit::where('employee_id', $application->employee_id)
                ->whereHas('leaveConfiguration', function ($query) use ($targetCode) {
                    $query->where('code', $targetCode);
                })
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            // 1. STRICT VALIDATION: Block if Wellness, SPL, or Force Leave balance is insufficient
            if (in_array($config->code, ['WL', 'SPL', 'FL'])) {
                if (!$credit || $credit->remaining_balance < $application->days_applied) {
                    return ['error' => 'Insufficient balance for ' . $config->name];
                }
            }

            $application->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            $noPayDays = $credit ? $credit->deductLeave((float) $application->days_applied) : (float) $application->days_applied;

            LeaveRecord::create([
                'employee_id'            => $application->employee_id,
                'leave_configuration_id' => $application->leave_configuration_id,
                'recorded_by'            => $request->user()->id,
                'start_date'             => $application->start_date,
                'end_date'               => $application->end_date,
                'days_taken'             => $application->days_applied,
                'no_pay_days'            => $noPayDays,
                'remarks'                => $noPayDays > 0
                    ? "Approved. {$noPayDays} day(s) Leave Without Pay."
                    : 'Approved. Balance deducted.',
            ]);

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'leave_application.approved',
                'description'  => "Approved leave application #{$application->id} ({$config->name})",
                'subject_type' => 'LeaveApplication',
                'subject_id'   => $application->id,
            ]);

            return ['error' => null];
        });

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Leave application approved successfully']);
    }
    public function reject(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is already ' . $application->status,
            ], 400);
        }

        $application->update([
            'status'      => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'leave_application.rejected',
            'description'  => "Rejected leave application #{$application->id}",
            'subject_type' => 'LeaveApplication',
            'subject_id'   => $application->id,
        ]);

        return response()->json(['message' => 'Leave application rejected successfully']);
    }
    // Add this method to your LeaveApplicationController
    private function validateLeaveEligibility($config, $employee)
    {
        if ($config->code === 'PTL' && $employee->sex !== 'male') {
            return 'Paternity leave is only available for male employees.';
        }

        if ($config->code === 'ML' && $employee->sex !== 'female') {
            return 'Maternity leave is only available for female employees.';
        }

        return null; // No errors
    }

    public function cancel(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is already ' . $application->status,
            ], 400);
        }

        $application->update([
            'status'      => 'cancelled',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);
        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'leave_application.cancelled',
            'description'  => "Cancelled leave application #{$application->id}",
            'subject_type' => 'LeaveApplication',
            'subject_id'   => $application->id,
        ]);

        return response()->json(['message' => 'Leave application cancelled successfully']);
    }

    public function show(Request $request, string $id)
    {
        // Vertical partitioning for show
        $application = LeaveApplication::select([
            'leave_applications.id',
            'leave_applications.employee_id',
            'leave_applications.leave_configuration_id',
            'leave_applications.start_date',
            'leave_applications.end_date',
            'leave_applications.days_applied',
            'leave_applications.reason',
            'leave_applications.status',
            'leave_applications.applied_at',
            'leave_applications.reviewed_at',
            'employees.first_name',
            'employees.surname',
            'leave_configurations.name as leave_type_name',
            'leave_configurations.code as leave_type_code',
        ])
            ->join('employees', 'leave_applications.employee_id', '=', 'employees.id')
            ->join('leave_configurations', 'leave_applications.leave_configuration_id', '=', 'leave_configurations.id')
            ->findOrFail($id);
        $user = $request->user();

        if (!$this->isAdmin($user) && $application->employee_id !== $user->employee?->id) {
            return response()->json([
                'message' => 'Unauthorized: You can only view your own leave applications.'
            ], 403);
        }

        return response()->json($application);
    }

    public function generatePdf(Request $request, $id)
    {
        $application = LeaveApplication::with([
            'employee.department',
            'leaveConfiguration',
            'reviewedBy'
        ])->findOrFail($id);

        $user = $request->user();

        if (!$this->isAdmin($user) && $application->employee_id !== $user->employee?->id) {
            return response()->json([
                'message' => 'Unauthorized: You can only generate PDF forms for your own leave applications.'
            ], 403);
        }

        $code = $application->leaveConfiguration->code;

        $configs = LeaveConfiguration::select(['id', 'code'])
            ->whereIn('code', ['VL', 'SL'])
            ->get()
            ->keyBy('code');

        // $credits = LeaveCredit::select(['leave_configuration_id', 'total_credits', 'remaining_balance'])
        //     ->where('employee_id', $application->employee_id)
        //     ->whereIn('leave_configuration_id', $configs->pluck('id'))
        //     ->where('year', now()->year)
        //     ->get()
        //     ->keyBy('leave_configuration_id');
        $year = Carbon::parse($application->start_date)->year;

        $credits = LeaveCredit::select(['leave_configuration_id', 'total_credits', 'remaining_balance'])
            ->where('employee_id', $application->employee_id)
            ->whereIn('leave_configuration_id', $configs->pluck('id'))
            ->where('year', $year)
            ->get()
            ->keyBy('leave_configuration_id');

        $vlId      = $configs->get('VL')?->id;
        $slId      = $configs->get('SL')?->id;
        $vlCredit  = $credits->get($vlId);
        $slCredit  = $credits->get($slId);

        $data = [
            'application' => $application,
            'code'        => $code,
            'vl_total'    => $vlCredit->total_credits ?? 0,
            'vl_balance'  => $vlCredit->remaining_balance ?? 0,


            'sl_total'    => $slCredit->total_credits ?? 0,
            'sl_balance'  => $slCredit->remaining_balance ?? 0,
        ];

        $pdf = Pdf::loadView('pdf.leave-application', $data);
        return $pdf->stream('leave-application-' . $application->id . '.pdf');
    }
}
