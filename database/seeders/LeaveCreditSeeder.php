<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LeaveCredit;
use App\Models\User;
use App\Models\Employee;
use App\Models\LeaveConfiguration;

class LeaveCreditSeeder extends Seeder
{
    public function run(): void
    {
        $year = now()->year;

        // Get sam blanza's employee record via user
        $user = User::where('email', 'samblanza@gmail.com')->first();

        if (!$user) {
            $this->command->warn('User sam blanza not found.');
            return;
        }

        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            $this->command->warn('No employee record linked to sam blanza.');
            return;
        }

        $vacationLeave = LeaveConfiguration::where('code', 'VL')->first();
        $sickLeave     = LeaveConfiguration::where('code', 'SL')->first();

        // 1.25 credits/month × 12 months = 15 days
        $credits = [
            [
                'leave_configuration_id' => $vacationLeave->id,
                'total_credits'          => 15.00,
                'used_credits'           => 0,
                'remaining_balance'      => 15.00,
            ],
            [
                'leave_configuration_id' => $sickLeave->id,
                'total_credits'          => 15.00,
                'used_credits'           => 0,
                'remaining_balance'      => 15.00,
            ],
        ];

        foreach ($credits as $credit) {
            $exists = LeaveCredit::where('employee_id', $employee->id)
                ->where('leave_configuration_id', $credit['leave_configuration_id'])
                ->where('year', $year)
                ->exists();

            if ($exists) {
                $this->command->warn("Credit already exists for config ID {$credit['leave_configuration_id']}, skipping.");
                continue;
            }

            LeaveCredit::create([
                'employee_id'            => $employee->id,
                'leave_configuration_id' => $credit['leave_configuration_id'],
                'total_credits'          => $credit['total_credits'],
                'used_credits'           => $credit['used_credits'],
                'remaining_balance'      => $credit['remaining_balance'],
                'year'                   => $year,
                'last_updated'           => now(),
            ]);
        }

        $this->command->info('Leave credits seeded for sam blanza!');
    }
}