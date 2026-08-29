<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\LeaveMonetization;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\DB;
use App\Models\Employee;

class LeaveMonetizationController extends Controller
{
    // CSC-style minimum balance that must remain after monetizing.
    // Adjust or remove this rule if your agency uses a different figure.
    private const MIN_RETAINED_BALANCE = 15;

    private function isAdmin($user): bool
    {
        return $user && in_array($user->role, ['hr_admin', 'super_admin'], true);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = LeaveMonetization::select([
            'leave_monetizations.id',
            'leave_monetizations.employee_id',
            'leave_monetizations.leave_configuration_id',
            'leave_monetizations.days_monetized',
            'leave_monetizations.approved_days',
            'leave_monetizations.reason',
            'leave_monetizations.rejection_reason',
            'leave_monetizations.status',
            'leave_monetizations.applied_at',
            'leave_monetizations.reviewed_at',
            'employees.first_name',
            'employees.surname',
            'leave_configurations.name as leave_type_name',
            'leave_configurations.code as leave_type_code',
        ])
            ->join('employees', 'leave_monetizations.employee_id', '=', 'employees.id')
            ->join('leave_configurations', 'leave_monetizations.leave_configuration_id', '=', 'leave_configurations.id')
            ->when(!$this->isAdmin($user), function ($q) use ($user) {
                $q->where('leave_monetizations.employee_id', $user->employee?->id);
            })
            ->when($this->isAdmin($user) && $request->filled('employee_id'), function ($q) use ($request) {
                $q->where('leave_monetizations.employee_id', $request->employee_id);
            })
            ->when($request->status, function ($q) use ($request) {
                $q->where('leave_monetizations.status', $request->status);
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($sub) use ($request) {
                    $sub->where('employees.first_name', 'LIKE', '%' . $request->search . '%')
                        ->orWhere('employees.surname', 'LIKE', '%' . $request->search . '%');
                });
            })
            ->when($request->filled('year'), function ($q) use ($request) {
                $q->whereYear('leave_monetizations.applied_at', $request->year);
            })
            ->orderBy('leave_monetizations.created_at', 'desc')
            ->paginate(15);

        return response()->json($query);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'leave_configuration_id' => 'required|exists:leave_configurations,id',
    //         'days_monetized'         => 'required|numeric|min:0.5',
    //         'reason'                 => 'nullable|string',
    //     ]);

    //     $employee = $request->user()->employee;
    //     if (!$employee) {
    //         return response()->json(['message' => 'Employee profile could not be determined.'], 422);
    //     }

    //     $config = LeaveConfiguration::findOrFail($validated['leave_configuration_id']);
    //     if (!$config->can_monetize) {
    //         return response()->json(['message' => $config->name . ' is not eligible for monetization.'], 422);
    //     }

    //     $credit = LeaveCredit::where('employee_id', $employee->id)
    //         ->where('leave_configuration_id', $config->id)
    //         ->where('year', now()->year)
    //         ->first();

    //     if (!$credit) {
    //         return response()->json(['message' => 'No leave credits found for this year.'], 422);
    //     }

    //     $balanceAfter = $credit->remaining_balance - $validated['days_monetized'];
    //     if ($balanceAfter < self::MIN_RETAINED_BALANCE) {
    //         return response()->json([
    //             'message' => 'Cannot monetize — must retain at least ' . self::MIN_RETAINED_BALANCE . ' days of ' . $config->name . '.'
    //         ], 422);
    //     }

    //     $monetization = LeaveMonetization::create([
    //         'employee_id'            => $employee->id,
    //         'leave_configuration_id' => $config->id,
    //         'days_monetized'         => $validated['days_monetized'],
    //         'reason'                 => $validated['reason'] ?? null,
    //         'status'                 => 'pending',
    //         'filed_by'               => $request->user()->id,
    //         'applied_at'             => now(),
    //     ]);

    //     return response()->json([
    //         'message' => 'Monetization request submitted successfully',
    //         'data'    => $monetization,
    //     ], 201);
    // }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id'            => 'nullable|exists:employees,id',
            'leave_configuration_id' => 'required|exists:leave_configurations,id',
            'days_monetized'         => 'required|numeric|min:0.5',
            'reason'                 => 'nullable|string',
        ]);

        $isAdminFiling = $request->filled('employee_id');

        // Only admins may file on behalf of another employee
        if ($isAdminFiling && !$this->isAdmin($request->user())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employee = $isAdminFiling
            ? Employee::find($validated['employee_id'])
            : $request->user()->employee;

        if (!$employee) {
            return response()->json(['message' => 'Target employee profile could not be determined.'], 422);
        }

        $config = LeaveConfiguration::findOrFail($validated['leave_configuration_id']);
        if (!$config->can_monetize) {
            return response()->json(['message' => $config->name . ' is not eligible for monetization.'], 422);
        }

        $credit = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $config->id)
            ->where('year', now()->year)
            ->first();

        if (!$credit) {
            return response()->json(['message' => 'No leave credits found for this year.'], 422);
        }

        $balanceAfter = $credit->remaining_balance - $validated['days_monetized'];
        if ($balanceAfter < self::MIN_RETAINED_BALANCE) {
            return response()->json([
                'message' => 'Cannot monetize — must retain at least ' . self::MIN_RETAINED_BALANCE . ' days of ' . $config->name . '.'
            ], 422);
        }

        // Admin filing on someone's behalf = auto-approved immediately.
        // Employee self-service = still goes to pending for admin review.
        if ($isAdminFiling) {
            $monetization = DB::transaction(function () use ($employee, $config, $validated, $request) {
                $credit = LeaveCredit::where('employee_id', $employee->id)
                    ->where('leave_configuration_id', $config->id)
                    ->where('year', now()->year)
                    ->lockForUpdate()
                    ->first();

                $m = LeaveMonetization::create([
                    'employee_id'            => $employee->id,
                    'leave_configuration_id' => $config->id,
                    'days_monetized'         => $validated['days_monetized'],
                    'approved_days'          => $validated['days_monetized'],
                    'reason'                 => $validated['reason'] ?? 'Filed by admin',
                    'status'                 => 'approved',
                    'filed_by'               => $request->user()->id,
                    'applied_at'             => now(),
                    'reviewed_by'            => $request->user()->id,
                    'reviewed_at'            => now(),
                ]);

                $credit->deductLeave((float) $validated['days_monetized']);

                ActivityLog::create([
                    'user_id'      => $request->user()->id,
                    'action'       => 'leave_monetization.filed_and_approved',
                    'description'  => "Admin filed and auto-approved monetization of {$validated['days_monetized']} day(s) for {$employee->first_name} {$employee->surname}",
                    'subject_type' => 'LeaveMonetization',
                    'subject_id'   => $m->id,
                ]);

                return $m;
            });

            return response()->json([
                'message' => 'Monetization filed and approved successfully',
                'data'    => $monetization,
            ], 201);
        }

        $monetization = LeaveMonetization::create([
            'employee_id'            => $employee->id,
            'leave_configuration_id' => $config->id,
            'days_monetized'         => $validated['days_monetized'],
            'reason'                 => $validated['reason'] ?? null,
            'status'                 => 'pending',
            'filed_by'               => $request->user()->id,
            'applied_at'             => now(),
        ]);

        return response()->json([
            'message' => 'Monetization request submitted successfully',
            'data'    => $monetization,
        ], 201);
    }

    public function approve(Request $request, $id)
    {
        $monetization = LeaveMonetization::findOrFail($id);

        if ($monetization->status !== 'pending') {
            return response()->json(['message' => 'Request is already ' . $monetization->status], 400);
        }

        // HR may approve fewer days than requested — budget shortfalls are
        // common — but never more. Omitting the field approves in full.
        $validated = $request->validate([
            'approved_days' => 'nullable|numeric|min:0.5|max:' . $monetization->days_monetized,
        ]);

        $approvedDays = (float) ($validated['approved_days'] ?? $monetization->days_monetized);

        $result = DB::transaction(function () use ($monetization, $request, $approvedDays) {

            $year = \Carbon\Carbon::parse($monetization->applied_at)->year;

            $credit = LeaveCredit::where('employee_id', $monetization->employee_id)
                ->where('leave_configuration_id', $monetization->leave_configuration_id)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (!$credit) {
                return ['error' => 'No leave credits found for this employee and year.'];
            }

            // Checked against the approved figure, not the requested one —
            // otherwise a fundable partial would be refused on the strength
            // of an amount nobody is actually deducting.
            $balanceAfter = $credit->remaining_balance - $approvedDays;
            if ($balanceAfter < self::MIN_RETAINED_BALANCE) {
                return ['error' => 'Insufficient balance to approve this monetization request.'];
            }

            $credit->deductLeave($approvedDays);

            $monetization->update([
                'status'        => 'approved',
                'approved_days' => $approvedDays,
                'reviewed_by'   => $request->user()->id,
                'reviewed_at'   => now(),
            ]);

            $note = $approvedDays < $monetization->days_monetized
                ? " (partial — {$monetization->days_monetized} day(s) requested)"
                : '';

            ActivityLog::create([
                'user_id'      => $request->user()->id,
                'action'       => 'leave_monetization.approved',
                'description'  => "Approved monetization of {$approvedDays} day(s) for employee #{$monetization->employee_id}{$note}",
                'subject_type' => 'LeaveMonetization',
                'subject_id'   => $monetization->id,
            ]);

            return ['error' => null];
        });

        if ($result['error']) {
            return response()->json(['message' => $result['error']], 422);
        }

        return response()->json(['message' => 'Monetization approved successfully']);
    }

    public function reject(Request $request, $id)
    {
        $monetization = LeaveMonetization::findOrFail($id);

        if ($monetization->status !== 'pending') {
            return response()->json(['message' => 'Request is already ' . $monetization->status], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        $monetization->update([
            'status'           => 'rejected',
            'rejection_reason' => $validated['rejection_reason'] ?? null,
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
        ]);

        ActivityLog::create([
            'user_id'      => $request->user()->id,
            'action'       => 'leave_monetization.rejected',
            'description'  => "Rejected monetization request #{$monetization->id}",
            'subject_type' => 'LeaveMonetization',
            'subject_id'   => $monetization->id,
        ]);

        return response()->json(['message' => 'Monetization request rejected successfully']);
    }
}
