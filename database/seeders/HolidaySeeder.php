<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Holiday;

class HolidaySeeder extends Seeder
{
    public function run(): void
    {
        $holidays = [
            ['date' => '2026-01-01', 'name' => "New Year's Day", 'is_recurring' => true],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday', 'is_recurring' => false],
            ['date' => '2026-04-03', 'name' => 'Good Friday', 'is_recurring' => false],
            ['date' => '2026-04-09', 'name' => 'Araw ng Kagitingan', 'is_recurring' => true],
            ['date' => '2026-05-01', 'name' => 'Labor Day', 'is_recurring' => true],
            ['date' => '2026-06-12', 'name' => 'Independence Day', 'is_recurring' => true],
            ['date' => '2026-08-21', 'name' => 'Ninoy Aquino Day', 'is_recurring' => true],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day', 'is_recurring' => false], // last Mon of Aug, varies
            ['date' => '2026-11-30', 'name' => 'Bonifacio Day', 'is_recurring' => true],
            ['date' => '2026-12-25', 'name' => 'Christmas Day', 'is_recurring' => true],
            ['date' => '2026-12-30', 'name' => 'Rizal Day', 'is_recurring' => true],
        ];

        foreach ($holidays as $holiday) {
            Holiday::updateOrCreate(['date' => $holiday['date']], $holiday);
        }
    }
}
