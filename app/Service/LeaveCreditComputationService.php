<?php

namespace App\Service;

use Carbon\Carbon;


class LeaveCreditComputationService
{
    public function minutesToEquivalentDays(int $totalMinutes): float
    {
        $hours = intval($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        // Hours are perfectly linear (1 hour = 0.125 days)
        $hoursDay = $hours * 0.125;

        // Complete, exact CSC Table IV mapping for minutes (1 to 59)
        $MinutesTable = [
            0  => 0.000,
            1  => 0.002,
            2  => 0.004,
            3  => 0.006,
            4  => 0.008,
            5  => 0.010,
            6  => 0.012,
            7  => 0.015,
            8  => 0.017,
            9  => 0.019,
            10 => 0.021,
            11 => 0.023,
            12 => 0.025,
            13 => 0.027,
            14 => 0.029,
            15 => 0.031,
            16 => 0.033,
            17 => 0.035,
            18 => 0.037,
            19 => 0.040,
            20 => 0.042,
            21 => 0.044,
            22 => 0.046,
            23 => 0.048,
            24 => 0.050,
            25 => 0.052,
            26 => 0.054,
            27 => 0.056,
            28 => 0.058,
            29 => 0.060,
            30 => 0.062,
            31 => 0.065,
            32 => 0.067,
            33 => 0.069,
            34 => 0.071,
            35 => 0.073,
            36 => 0.075,
            37 => 0.077,
            38 => 0.079,
            39 => 0.081,
            40 => 0.083,
            41 => 0.085,
            42 => 0.087,
            43 => 0.090,
            44 => 0.092,
            45 => 0.094,
            46 => 0.096,
            47 => 0.098,
            48 => 0.100,
            49 => 0.102,
            50 => 0.104,
            51 => 0.106,
            52 => 0.108,
            53 => 0.110,
            54 => 0.112,
            55 => 0.115,
            56 => 0.117,
            57 => 0.119,
            58 => 0.121,
            59 => 0.123,
            60 => 0.125
        ];

        // Retrieve the exact mapped value, default to 0.000 if not found
        $minutesDay = $MinutesTable[$minutes] ?? 0.000;

        return $hoursDay + $minutesDay;
    }

    /**
     * CSC Table III Lookup: Leave Credits Earned based on Days Present.
     * Hardcoded 1-to-29 mapping to ensure 100% compliance with CSC guidelines.
     */
    public function getLeaveCredit(float $daysPresent): float
    {
        if ($daysPresent <= 0) return 0.000;
        if ($daysPresent >= 30) return 1.250;

        // Exact values from Table III for 1 to 29 days present
        $DaysTable = [
            1  => 0.042,
            2  => 0.083,
            3  => 0.125,
            4  => 0.167,
            5  => 0.208,
            6  => 0.250,
            7  => 0.292,
            8  => 0.333,
            9  => 0.375,
            10 => 0.417,
            11 => 0.458,
            12 => 0.500,
            13 => 0.542,
            14 => 0.583,
            15 => 0.625,
            16 => 0.667,
            17 => 0.708,
            18 => 0.750,
            19 => 0.792,
            20 => 0.833,
            21 => 0.875,
            22 => 0.917,
            23 => 0.958,
            24 => 1.000,
            25 => 1.042,
            26 => 1.083,
            27 => 1.125,
            28 => 1.167,
            29 => 1.208
        ];

        $days = (int) round($daysPresent);

        return $DaysTable[$days] ?? 0.000;
    }

    /**
     * Main computation method.
     */
    public function computeMonthlyCredits(array $data): array
    {
        // Standard CSC convention: always treat the month as 30 days for credit computation
        $standardMonthDays = 30;

        $absentWithLeave = $data['absent_with_leave_days'] ?? 0;
        $absentWithoutLeave = $data['absent_without_leave_days'] ?? 0;

        $isCasual = ($data['employment_status'] ?? null) === 'casual';

        if ($isCasual && $absentWithoutLeave > 0) {
            $daysPresentForCredit = $standardMonthDays - $absentWithoutLeave;
            $vlEarned = $this->getLeaveCredit($daysPresentForCredit);
            $slEarned = $this->getLeaveCredit($daysPresentForCredit);
        } else {
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
