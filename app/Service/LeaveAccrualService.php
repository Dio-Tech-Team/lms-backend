<?php

namespace App\Service;

use App\Models\Employee;
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

        // Jump straight to the month of $after instead of looping from the hire date
        $n = max(1, ($after->year - $hired->year) * 12 + ($after->month - $hired->month));

        $posted = 0;
        while (true) {
            // Always computed from the hire date, never chained, so a 31st hire
            // gets Feb 28/29 and then returns to the 31st in March
            $date = $hired->copy()->addMonthsNoOverflow($n);
            if ($date->gt($today)) break;

            if ($date->gt($after) && $this->post($employee, $date)) {
                $posted++;
            }
            $n++;
        }

        return $posted;
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

        return DB::transaction(function () use ($employee, $date, $year) {
            $inserted = LeaveAccrual::insertOrIgnore([
                'employee_id'   => $employee->id,
                'accrual_month' => $date->copy()->startOfMonth()->toDateString(),
                'accrual_date'  => $date->toDateString(),
                'vl_earned'     => self::MONTHLY,
                'sl_earned'     => self::MONTHLY,
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

                $credit->total_credits = round((float) $credit->total_credits + self::MONTHLY, 3);
                $credit->last_updated  = now();
                $credit->save(); // saving hook recomputes balance, saved hook syncs carry-over
            }

            return true;
        });
    }
}
