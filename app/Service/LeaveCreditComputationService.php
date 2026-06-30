<?php

namespace App\Service;

use Carbon\Carbon;


class LeaveCreditComputationService
{
    /**
     * Create a new class instance.
     */
    // public function __construct()
    // {
    //     //
    // }

    public function getTotalWorkingDays(int $month, int $year): int
    {

        $startDate = Carbon::create($year, $month, 1);
        $endDate = $startDate->copy()->endOfMonth();

        $workingDays = 0;
        $current = $startDate->copy();


        while ($current->lte($endDate)) {
            if (!$current->isWeekend()) {
                $workingDays++;
            }
            $current->addDay();
        }

        return $workingDays;
    }
    //   Convert minutes to equivalent day decimal (Table from CSC: based on 8-hour workday).
    //   1 minute = 0.002 day (approximately, since 8 hours = 480 minutes = 1.000 day)
    //  
    public function minutesToEquivalentDays(int $totalMinutes): float
    {
        // 480 minutes = 1.000 day (8-hour workday)
        return round($totalMinutes / 480, 3);
    }

    /**
     * Table III Lookup: Leave Credits Earned based on Days Present (when there's LWOP).
     * Formula derived from Table III: credit = (days_present / 30) * 1.25
     */
    public function getLeaveCredit(float $daysPresent): float
    {
        // Table III is essentially a proportional scale based on 30 days = 1.250 credit
        $credit = ($daysPresent / 30) * 1.25;
        return round(max(0, $credit), 3);
    }
    /**
     * Main computation method.
     */
    // public function computeMonthlyCredits(array $data): array
    // {
    //     $totalWorkingDays = $this->getTotalWorkingDays($data['month'], $data['year']);

    //     $absentWithLeave = $data['absent_with_leave_days'] ?? 0;
    //     $absentWithoutLeave = $data['absent_without_leave_days'] ?? 0;

    //     // Step 2: Days Present (raw)
    //     $daysPresent = $totalWorkingDays - $absentWithLeave - $absentWithoutLeave;

    //     // Step 3: Determine VL/SL Earned
    //     if ($absentWithoutLeave > 0) {
    //         // Scenario B: has LWOP, use Table III lookup
    //         $adjustedDaysPresentForCredit = $totalWorkingDays - $absentWithoutLeave;
    //         $vlEarned = $this->getLeaveCredit($adjustedDaysPresentForCredit);
    //         $slEarned = $this->getLeaveCredit($adjustedDaysPresentForCredit);
    //     } else {
    //         // Scenario A: no LWOP, flat rate
    //         $vlEarned = 1.250;
    //         $slEarned = 1.250;
    //     }

    //     // if ($absentWithoutLeave > 0) {
    //     //     // Force the baseline to 30 days as required by the CSC Table III matrix
    //     //     $DaysPresent = 30 - $absentWithoutLeave;

    //     //     $vlEarned = $this->getLeaveCredit($DaysPresent);
    //     //     $slEarned = $this->getLeaveCredit($DaysPresent);
    //     // } else {
    //     //     $vlEarned = 1.250;
    //     //     $slEarned = 1.250;
    //     // }

    //     // Step 4: Tardiness Equivalent Days
    //     $totalLateUndertimeMinutes =
    //         ($data['late_am_minutes'] ?? 0) +
    //         ($data['late_pm_minutes'] ?? 0) +
    //         ($data['undertime_am_minutes'] ?? 0) +
    //         ($data['undertime_pm_minutes'] ?? 0);
    //     $tardinessEquivalentDays = $this->minutesToEquivalentDays($totalLateUndertimeMinutes);

    //     return [
    //         'total_working_days'        => $totalWorkingDays,
    //         'days_present'               => $daysPresent,
    //         'vl_earned'                  => $vlEarned,
    //         'sl_earned'                  => $slEarned,
    //         'tardiness_equivalent_days'  => $tardinessEquivalentDays,
    //     ];
    // }

    public function computeMonthlyCredits(array $data): array
    {
        // Standard CSC convention: always treat the month as 30 days for credit computation
        $standardMonthDays = 30;

        $absentWithLeave = $data['absent_with_leave_days'] ?? 0;
        $absentWithoutLeave = $data['absent_without_leave_days'] ?? 0;

        // Step 3: Determine VL/SL Earned
        if ($absentWithoutLeave > 0) {
            // Scenario B: has LWOP, use Table III lookup
            $daysPresentForCredit = $standardMonthDays - $absentWithoutLeave;
            $vlEarned = $this->getLeaveCredit($daysPresentForCredit);
            $slEarned = $this->getLeaveCredit($daysPresentForCredit);
        } else {
            // Scenario A: no LWOP, flat rate
            $vlEarned = 1.250;
            $slEarned = 1.250;
        }

        // Step 4: Tardiness Equivalent Days
        $totalLateUndertimeMinutes =
            ($data['late_am_minutes'] ?? 0) +
            ($data['late_pm_minutes'] ?? 0) +
            ($data['undertime_am_minutes'] ?? 0) +
            ($data['undertime_pm_minutes'] ?? 0);

        $tardinessEquivalentDays = $this->minutesToEquivalentDays($totalLateUndertimeMinutes);

        return [
            'standard_month_days'       => $standardMonthDays,
            'vl_earned'                 => $vlEarned,
            'sl_earned'                 => $slEarned,
            'tardiness_equivalent_days' => $tardinessEquivalentDays,
        ];
    }
}
