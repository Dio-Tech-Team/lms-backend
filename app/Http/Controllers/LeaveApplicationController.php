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

class LeaveApplicationController extends Controller
{
    public function index(Request $request)
    {
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
            'leave_configurations.name as leave_type_name',
            'leave_configurations.code as leave_type_code',
            'users.username as reviewed_by_username',
        ])
            ->join('employees', 'leave_applications.employee_id', '=', 'employees.id')
            ->join('leave_configurations', 'leave_applications.leave_configuration_id', '=', 'leave_configurations.id')
            ->leftJoin('users', 'leave_applications.reviewed_by', '=', 'users.id') // LEFT JOIN since reviewer may be null
            ->when($request->status, function ($query) use ($request) {
                $query->where('leave_applications.status', $request->status); // uses status index!
            })
            ->orderBy('leave_applications.created_at', 'desc')
            ->paginate(10);

        return response()->json($applications);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'leave_configuration_id' => 'required|exists:leave_configurations,id',
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'days_applied'    => 'required|numeric|min:0.5',
            'reason'          => 'nullable|string',
        ]);

        $employee = $request->user()->employee;

        if (!$employee) {
            return response()->json([
                'message' => 'The authenticated user is not linked to an employee profile.'
            ], 402);
        }

        // Fetch leave config once (store intermediate result)
        $config = LeaveConfiguration::select(['id', 'code', 'name'])
            ->findOrFail($validated['leave_configuration_id']);

        if ($config->code === 'WL' && $validated['days_applied'] > 3) {
            return response()->json([
                'message' => 'Wellness leave cannot exceed 3 consecutive days per application.'
            ], 422);
        }

        // FIXED: correct column names for credit lookup
        $credit = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $validated['leave_configuration_id'])
            ->where('year', now()->year)
            ->first();

        // Warn if insufficient balance (but still allow submission)
        $hasInsufficientBalance = !$credit || $credit->remaining_balance < $validated['days_applied'];

        $application = LeaveApplication::create([
            'employee_id'     => $employee->id,
            'leave_configuration_id' => $validated['leave_configuration_id'],
            'start_date'      => $validated['start_date'],
            'end_date'        => $validated['end_date'],
            'days_applied'    => $validated['days_applied'],
            'reason'          => $validated['reason'],
            'status'          => 'pending',
            'applied_at'      => now(),
        ]);

        return response()->json([
            'message'              => 'Leave application submitted successfully',
            'data'                 => $application,
            'insufficient_balance' => $hasInsufficientBalance,
            'remaining_balance'    => $credit->remaining_balance ?? 0,
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $application = LeaveApplication::findOrFail($id);

        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application is already ' . $application->status,
            ], 400);
        }

        // FIXED: correct column name leave_configuration_id
        $credit = LeaveCredit::where('employee_id', $application->employee_id)
            ->where('leave_configuration_id', $application->leave_configuration_id)
            ->where('year', now()->year)
            ->first();

        // OPTIMIZED: wrap both writes in a transaction
        DB::transaction(function () use ($application, $request, $credit) {
            $application->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            LeaveRecord::create([
                'employee_id'     => $application->employee_id,
                'leave_configuration_id' => $application->leave_configuration_id,
                'recorded_by'     => $request->user()->id,
                'start_date'      => $application->start_date,
                'end_date'        => $application->end_date,
                'days_taken'      => $application->days_applied,
                'remarks'         => $application->id,
            ]);

            if ($credit) {
                $credit->used_credits      += $application->days_applied;
                $credit->remaining_balance -= $application->days_applied;
                $credit->last_updated       = now();
                $credit->save();
            }
        });

        return response()->json(['message' => 'Leave application approved successfully']);
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

        return response()->json(['message' => 'Leave application cancelled successfully']);
    }

    public function show(string $id)
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

        return response()->json($application);
    }

    public function generatePdf($id)
    {
        $application = LeaveApplication::with([
            'employee.department',
            'leaveConfiguration',
            'reviewedBy'
        ])->findOrFail($id);

        $code = $application->leaveConfiguration->code;

        // OPTIMIZED: fetch VL and SL configs in ONE query instead of two separate whereHas()
        $configs = LeaveConfiguration::select(['id', 'code'])
            ->whereIn('code', ['VL', 'SL'])
            ->get()
            ->keyBy('code');

        // OPTIMIZED: fetch both credits in ONE query instead of two
        $credits = LeaveCredit::select(['leave_configuration_id', 'total_credits', 'remaining_balance'])
            ->where('employee_id', $application->employee_id)
            ->whereIn('leave_configuration_id', $configs->pluck('id'))
            ->where('year', now()->year)
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
