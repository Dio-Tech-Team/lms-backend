<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    private static array $departmentCounters = [];

    public function definition(): array
    {
        return [
            'department_id' => Department::inRandomOrder()->first()?->id,
            'user_id' => User::factory()->state(['role' => 'employee']),
            'first_name' => 'Temporary',
            'middle_name' => 'M',
            'surname' => 'Temporary',
            'id_number' => 'TMP-' . $this->faker->unique()->numberBetween(10000, 99999),
            'birthdate' => $this->faker->date('Y-m-d', '2002-01-01'),
            // 'place_of_birth' => 'Echague, Isabela',
            'place_of_birth' => 'Echague, Isabela', // acts as a fallback default

            'sex' => $this->faker->randomElement(['male', 'female']),
            'civil_status' => $this->faker->randomElement(['single', 'married', 'widowed', 'separated']),
            'height' => "5'5",
            'weight' => 65,
            'bloodtype' => 'O',
            'highest_educational_attainment' => 'college',
            // 'residential_address' => 'Echague, Isabela',
            'residential_address' => 'Echague, Isabela',
            'contact_number' => '09' . $this->faker->numerify('#########'),
            'umid_id' => '1234-1234-1234-1234',
            'pagibig_id' => '1234-1234-1234-1234',
            'philhealth_number' => '1234-1234-1234-1234',
            'psn_number' => '1234-1234-1234-1234',
            'tin_number' => '1234-1234-1234-1234',
            'employment_status' => 'permanent',
            'position' => 'Staff',
            'date_hired' => $this->faker->dateTimeBetween('2010-01-01', '2025-12-31')->format('Y-m-d'),
            'is_active' => true,
        ];
    }

    public function configure()
    {
        return $this->afterMaking(function (Employee $employee) {
            $department = Department::find($employee->department_id);
            $prefix = $department ? $department->code : 'EMP';

            if (!isset(self::$departmentCounters[$prefix])) {
                self::$departmentCounters[$prefix] = 1;
            } else {
                self::$departmentCounters[$prefix]++;
            }
            $employee->id_number = $prefix . '-' . str_pad(self::$departmentCounters[$prefix], 2, '0', STR_PAD_LEFT);

            // Job Assignment Matrix
            switch ($prefix) {
                case 'MO':
                    $pool = [
                        ['title' => 'Municipal Administrator', 'status' => 'permanent'],
                        ['title' => 'Private Secretary', 'status' => 'casual'],
                        ['title' => 'Administrative Aide IV', 'status' => 'job_order'],
                    ];
                    break;
                case 'MHO':
                    $pool = [
                        ['title' => 'Municipal Health Officer', 'status' => 'permanent'],
                        ['title' => 'Nurse II', 'status' => 'permanent'],
                        ['title' => 'Health Aide', 'status' => 'job_order'],
                    ];
                    break;
                default:
                    $pool = [
                        ['title' => 'Administrative Officer I', 'status' => 'permanent'],
                        ['title' => 'Clerk III', 'status' => 'casual'],
                        ['title' => 'Utility Worker I', 'status' => 'job_order'],
                    ];
                    break;
            }

            $selectedJob = $this->faker->randomElement($pool);
            $employee->position = $selectedJob['title'];
            $employee->employment_status = $selectedJob['status'];

            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $employee->first_name));
            $cleanSurname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $employee->surname));
            $username = ($cleanFirst !== 'temporary') ? ($cleanFirst . $cleanSurname) : 'user' . rand(1000, 9999);

            if ($employee->user) {
                $employee->user->username = $username;
                $employee->user->email = $username . '@echague.gov.ph';
            }
        });
    }
}
