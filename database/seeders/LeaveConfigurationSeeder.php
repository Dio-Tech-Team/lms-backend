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
                'application_to' => ['all'],
                'can_carry_over' => true,
                'can_monetize' => true,
                'fixed_days' => null,
                'monthly_credit' => 1.25,
                'credit_type' => 'monthly',
                'description' => 'Leave for personal vacation and rest purpose'
            ],
            [
                'name'           => 'Sick Leave',
                'code'           => 'SL',
                'application_to'  => ['all'],
                'can_carry_over' => true,
                'can_monetize'   => true,
                'fixed_days'     => null,
                'monthly_credit' => 1.25,
                'credit_type'    => 'monthly',
                'description'    => 'Leave for illness or medical reasons',
            ],
            [
                'name'           => 'Wellness Leave',
                'code'           => 'WL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 5,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'description'    => '5 days of wellness leave per year for employees to recuperate and focus on health.',
            ],
            [
                'name'           => 'Mandatory/Forced Leave',
                'code'           => 'FL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 0,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'description'    => 'Annual 5-day forced leave, forfeited if not availed within the year unless the head of agency cancels it in the exigency of service',
            ],
            [
                'name'           => 'Maternity Leave',
                'code'           => 'ML',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 105,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'For female employees. Requires proof of pregnancy and, if applicable, accomplished Notice of Allocation of Maternity Leave Credits (CS Form No. 6a)',
            ],
            [
                'name'           => 'Paternity Leave',
                'code'           => 'PTL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 7,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'For male employees. Requires proof of child\'s delivery e.g. birth certificate, medical certificate and marriage contract',
            ],
            [
                'name'           => 'Special Privilege Leave',
                'code'           => 'SPL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 3,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'description'    => 'Must be filed/approved at least 1 week prior to availment, except in emergency cases, for personal milestones, anniversaries, and similar occasions',
            ],
            [
                'name'           => 'Solo Parent Leave',
                'code'           => 'SOL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 7,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Must be filed at least 1 week in advance with updated Solo Parent Identification Card',
            ],
            [
                'name'           => 'Study Leave',
                'code'           => 'STL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 180,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Up to 6 months. Subject to agency internal requirements and a contract between the agency head/authorized representative and the employee',
            ],
            [
                'name'           => 'VAWC Leave',
                'code'           => 'VAWC',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 10,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'For woman employees under RA 9262. Requires BPO/TPO/PPO or barangay/prosecutor/court certification that a case is pending',
            ],
            [
                'name'           => 'Rehabilitation Leave',
                'code'           => 'RHL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 180,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Up to 6 months. Requires application within 1 week of the accident, supporting reports, medical certificate, and written physician concurrence',
            ],
            [
                'name'           => 'Special Leave Benefits for Women',
                'code'           => 'SLB',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 60,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Up to 2 months for women who undergo gynecological surgery, under RA 9710. Filed at least 5 days prior, accompanied by a medical certificate',
            ],
            [
                'name'           => 'Special Emergency (Calamity) Leave',
                'code'           => 'CAL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => 5,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Up to 5 working days within one year of a declared calamity in the employee\'s area of residence, subject to head of agency validation',
            ],
            [
                'name'           => 'Adoption Leave',
                'code'           => 'ADL',
                'application_to' => ['all'],
                'can_carry_over' => false,
                'can_monetize'   => false,
                'fixed_days'     => null,
                'monthly_credit' => null,
                'credit_type'    => 'fixed',
                'grant_type'     => 'event_manual',
                'description'    => 'Duration per the Pre-Adoptive Placement Authority (PAPA) issued by DSWD — requires authenticated copy',
            ],
        ];

        foreach ($leaves as $leave) {
            LeaveConfiguration::create($leave);
        }
    }
}
