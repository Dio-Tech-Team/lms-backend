<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LeaveConfiguration;

class LeaveConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $leaves = [
            [
                'name' => 'Vacation Leave',
                'code' => 'VL',
                'application_to' => 'all',
                'can_carry_over' => true,
                'can_monetize' => true,
                'fixed_days' => null,
                'monthly_credit' => 1.25,
                'credit_type' => 'monthly',
                'description' => 'Leave for personal vacation and rest purposes'
            ],
            [
                'name'           => 'Sick Leave',
                'code'           => 'SL',
                'application_to'  => 'all',
                'can_carry_over' => false,
                'can_monetize'   => true,
                'fixed_days'     => null,
                'monthly_credit' => 1.25,
                'credit_type'    => 'monthly',
                'description'    => 'Leave for illness or medical reasons',
            ],
        ];

        foreach ($leaves as $leave) {
            LeaveConfiguration::create($leave);
        }
    }
}
