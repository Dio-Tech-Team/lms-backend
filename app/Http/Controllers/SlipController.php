<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Slip;
use App\Models\Employee;
use App\Models\LeaveCredit;
use App\Models\LeaveConfiguration;
use App\Models\LeaveRecord;
use App\Models\ActivityLog;
use App\Service\LeaveCreditComputationService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SlipController extends Controller
{
    public function __construct(protected LeaveCreditComputationService $computationService) {}

    public function index(Request $request)
    {
        $request->validate([
            'year'   => 'nullable|integer',
            'month'  => 'nullable|integer|min:1|max:12',
            'search' => 'nullable|string',
        ]);

        $slips = Slip::with('employee:id,first_name,middle_name,surname,id_number', 'recorder:id,username')
            ->when($request->filled('year'), fn($q) => $q->whereYear('date', $request->year))
            ->when($request->filled('month'), fn($q) => $q->whereMonth('date', $request->month))
            ->when($request->filled('search'), function ($q) use ($request) {
                $q->whereHas('employee', function ($e) use ($request) {
                    $e->where('first_name', 'LIKE', "%{$request->search}%")
                        ->orWhere('surname', 'LIKE', "%{$request->search}%");
                });
            })
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json($slips);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'date'        => 'required|date|before_or_equal:today',
            'time_out'    => 'required|date_format:H:i',
            'time_in'     => 'required|date_format:H:i|after:time_out',
            'reason'      => 'nullable|string|max:255',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);

        if (!$employee->is_active || $employee->employment_status === 'job_order') {
            return response()->json(['message' => 'Job Order or inactive employees have no VL to deduct from.'], 422);
        }

        $onLeave = \App\Models\LeaveApplication::where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $validated['date'])
            ->whereDate('end_date', '>=', $validated['date'])
            ->exists();

        if ($onLeave) {
            return response()->json(['message' => 'Employee is on approved leave on this date — a personal slip cannot be recorded.'], 422);
        }

        $out     = Carbon::parse("{$validated['date']} {$validated['time_out']}");
        $in      = Carbon::parse("{$validated['date']} {$validated['time_in']}");
        $minutes = (int) abs($in->diffInMinutes($out));
        $days    = $this->computationService->minutesToEquivalentDays($minutes);
        $year    = Carbon::parse($validated['date'])->year;

        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();

        $slip = DB::transaction(function () use ($validated, $employee, $minutes, $days, $year, $vlConfig, $request) {
            $vlCredit = LeaveCredit::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $vlConfig->id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$vlCredit) {
                abort(response()->json(['message' => "No VL credits initialized for {$year}."], 422));
            }

            $noPay = round($vlCredit->deductLeave($days), 3);

            $slip = Slip::create([
                'employee_id'     => $employee->id,
                'date'            => $validated['date'],
                'time_out'        => $validated['time_out'],
                'time_in'         => $validated['time_in'],
                'minutes'         => $minutes,
                'equivalent_days' => $days,
                'lwop_days'       => $noPay,
                'reason'          => $validated['reason'] ?? null,
                'recorded_by'     => $request->user()->id,
            ]);

            // VL couldn't cover it — same LWOP pattern as tardiness
            if ($noPay > 0) {
                LeaveRecord::create([
                    'employee_id'            => $employee->id,
                    'leave_configuration_id' => $vlConfig->id,
                    'slip_id'                => $slip->id,
                    'recorded_by'            => $request->user()->id,
                    'start_date'             => $validated['date'],
                    'end_date'               => $validated['date'],
                    'days_taken'             => $noPay,
                    'no_pay_days'            => $noPay,
                    'remarks'                => "Personal slip ({$minutes} min) exceeded available VL balance by {$noPay} day(s) — recorded as LWOP.",
                ]);
            }

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'slip.recorded',
                'description'  => "Recorded personal slip for {$employee->first_name} {$employee->surname} — {$minutes} min ({$days} day) on {$validated['date']}",
                'subject_type' => 'Slip',
                'subject_id'   => $slip->id,
            ]);

            return $slip;
        });

        return response()->json([
            'message' => 'Personal slip recorded',
            'slip'    => $slip->load('employee:id,first_name,surname'),
        ], 201);
    }

    public function cancel(Request $request, $id)
    {
        $slip = Slip::with('employee:id,first_name,surname')->findOrFail($id);

        if ($slip->status === 'cancelled') {
            return response()->json(['message' => 'Slip is already cancelled.'], 422);
        }

        $vlConfig = LeaveConfiguration::where('code', 'VL')->first();

        DB::transaction(function () use ($slip, $vlConfig, $request) {
            $vlCredit = LeaveCredit::where('employee_id', $slip->employee_id)
                ->where('leave_configuration_id', $vlConfig->id)
                ->where('year', $slip->date->year)
                ->lockForUpdate()
                ->first();

            // Only the part VL absorbed went into used_credits
            if ($vlCredit) {
                $absorbed = $slip->equivalent_days - $slip->lwop_days;
                $vlCredit->used_credits = max(0, (float) $vlCredit->used_credits - $absorbed);
                $vlCredit->last_updated = now();
                $vlCredit->save();
            }

            LeaveRecord::where('slip_id', $slip->id)->delete();

            $slip->update([
                'status'       => 'cancelled',
                'cancelled_by' => $request->user()->id,
                'cancelled_at' => now(),
            ]);

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'slip.cancelled',
                'description'  => "Cancelled personal slip for {$slip->employee->first_name} {$slip->employee->surname} on {$slip->date->format('Y-m-d')} — VL restored.",
                'subject_type' => 'Slip',
                'subject_id'   => $slip->id,
            ]);
        });

        return response()->json(['message' => 'Slip cancelled, VL restored']);
    }
}
