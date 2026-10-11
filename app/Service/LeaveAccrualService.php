<?php

namespace App\Service;

use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\LeaveAccrual;
use App\Models\LeaveConfiguration;
use App\Models\LeaveCredit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveAccrualService
{
    public const MONTHLY = 1.250;

    private ?LeaveConfiguration $vl = null;
    private ?LeaveConfiguration $sl = null;

    private function configs(): bool
    {
        $this->vl ??= LeaveConfiguration::where('code', 'VL')->first();
        $this->sl ??= LeaveConfiguration::where('code', 'SL')->first();
        return $this->vl && $this->sl;
    }

    public function accrueAll(): int
    {
        $posted = 0;

        Employee::where('is_active', true)
            ->where('employment_status', '!=', 'job_order')
            ->whereNotNull('date_hired')
            ->chunkById(100, function ($employees) use (&$posted) {
                foreach ($employees as $employee) {
                    $posted += $this->accrueForEmployee($employee);
                }
            });

        return $posted;
    }

    /**
     * Posts every monthly anniversary that has passed and isn't recorded yet.
     * Safe to call repeatedly — the unique key on (employee_id, accrual_month)
     * blocks duplicates.
     */
    public function accrueForEmployee(Employee $employee): int
    {
        if (!$employee->is_active || $employee->employment_status === 'job_order' || !$employee->date_hired) {
            return 0;
        }
        if (!$this->configs()) return 0;

        // PH calendar date, at midnight in the app timezone, so it compares
        // cleanly with date_hired / opening_balance_date
        $today = Carbon::parse(Carbon::now('Asia/Manila')->toDateString());
        $hired = Carbon::parse($employee->date_hired)->startOfDay();

        // Accruals on or before the paper-card transfer date are already in the opening balance
        $openingDate = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $this->vl->id)
            ->whereNotNull('opening_balance_date')
            ->min('opening_balance_date');

        $registeredOn = Carbon::parse(Carbon::parse($employee->created_at)->timezone('Asia/Manila')->toDateString());
        if (!$openingDate && $hired->lt($registeredOn)) {
            return 0;
        }
        $firstYear = LeaveCredit::where('employee_id', $employee->id)
            ->where('leave_configuration_id', $this->vl->id)
            ->min('year');
        if (!$firstYear) return 0;

        // An accrual counts only if its date is strictly after $after
        $after = $hired->copy();
        if ($openingDate && Carbon::parse($openingDate)->gt($after)) {
            $after = Carbon::parse($openingDate)->startOfDay();
        }
        $floor = Carbon::create($firstYear, 1, 1)->subDay();
        if ($floor->gt($after)) {
            $after = $floor;
        }

        // Rehired: no credits for the months spent resigned
        $rehiredOn = EmploymentHistory::where('employee_id', $employee->id)
            ->where('previous_employment_status', 'resigned')
            ->max('effective_date');
        if ($rehiredOn && Carbon::parse($rehiredOn)->startOfDay()->gt($after)) {
            $after = Carbon::parse($rehiredOn)->startOfDay();
        }

        // Jump straight to the month of $after instead of looping from the hire date
        $n = max(1, ($after->year - $hired->year) * 12 + ($after->month - $hired->month));

        $posted = 0;
        while (true) {
            // Always computed from the hire date, never chained, so a 31st hire
            // gets Feb 28/29 and then returns to the 31st in March
            $date = $hired->copy()->addMonthsNoOverflow($n);
            if ($date->gt($today)) break;

            // Casual whose appointment ended before this month closed: hold the
            // credit until HR records the renewal (it then posts on this same date)
            if (
                $employee->employment_status === 'casual'
                && $employee->appointment_end
                && Carbon::parse($employee->appointment_end)->lt($date->copy()->subDay())
            ) {
                break;
            }

            if ($date->gt($after) && $this->post($employee, $date)) {
                $posted++;
            }
            $n++;
        }

        return $posted;
    }

    /**
     * Credit for the month this date closes. A casual with days in that
     * month without appointment (late renewal) earns per the CSC table:
     * 30 − gap days present. Everyone else earns the full 1.250.
     */
    private function earnedFor(Employee $employee, Carbon $date): float
    {
        if ($employee->employment_status !== 'casual') return self::MONTHLY;

        // The month this credit covers: one month back up to the day before
        $periodStart = $date->copy()->subMonthNoOverflow();
        $periodEnd   = $date->copy()->subDay();

        $gapDays = 0;
        $renewals = EmploymentHistory::where('employee_id', $employee->id)
            ->where('gap_days', '>', 0)
            ->get(['effective_date', 'gap_days']);

        foreach ($renewals as $r) {
            // Gap = the gap_days just before the renewal date
            $gapEnd   = Carbon::parse($r->effective_date)->startOfDay()->subDay();
            $gapStart = $gapEnd->copy()->subDays($r->gap_days - 1);

            $from = $gapStart->gt($periodStart) ? $gapStart : $periodStart;
            $to   = $gapEnd->lt($periodEnd) ? $gapEnd : $periodEnd;

            if ($from->lte($to)) {
                $gapDays += (int) $from->diffInDays($to) + 1;
            }
        }

        if ($gapDays === 0) return self::MONTHLY;

        return app(LeaveCreditComputationService::class)->getLeaveCredit(max(0, 30 - $gapDays));
    }

    private function post(Employee $employee, Carbon $date): bool
    {
        $year = $date->year;

        $hasRows = LeaveCredit::where('employee_id', $employee->id)
            ->whereIn('leave_configuration_id', [$this->vl->id, $this->sl->id])
            ->where('year', $year)
            ->count() === 2;

        // Year not initialized yet — left unrecorded, so it posts on a later run
        if (!$hasRows) return false;

        $earned = $this->earnedFor($employee, $date);

        return DB::transaction(function () use ($employee, $date, $year, $earned) {
            $inserted = LeaveAccrual::insertOrIgnore([
                'employee_id'   => $employee->id,
                'accrual_month' => $date->copy()->startOfMonth()->toDateString(),
                'accrual_date'  => $date->toDateString(),
                'vl_earned'     => $earned,
                'sl_earned'     => $earned,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            if ($inserted === 0) return false; // already credited for this month

            foreach ([$this->vl, $this->sl] as $config) {
                $credit = LeaveCredit::where('employee_id', $employee->id)
                    ->where('leave_configuration_id', $config->id)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->first();

                $credit->total_credits = round((float) $credit->total_credits + $earned, 3);
                $credit->last_updated  = now();
                $credit->save(); // saving hook recomputes balance, saved hook syncs carry-over
            }

            return true;
        });
    }
}
