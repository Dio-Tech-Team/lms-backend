<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use App\Models\EmploymentHistory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::where('code', 'MASO')->first();

        if (!$department) {
            $this->command->error('MASO department not found. Please run DepartmentSeeder first.');
            return;
        }

        $employees = [
            ['first_name' => 'Jujie', 'surname' => 'Furuc', 'middle_name' => 'C.'],
            ['first_name' => 'Bernadette', 'surname' => 'Rosete', 'middle_name' => 'C.'],
            ['first_name' => 'Nem', 'surname' => 'Castillo', 'middle_name' => 'G.'],
            ['first_name' => 'Nelson', 'surname' => 'Catabian', 'middle_name' => 'M.'],
            ['first_name' => 'Ryan', 'surname' => 'Agustin', 'middle_name' => 'C.'],
            ['first_name' => 'Flordelyn', 'surname' => 'Navarro', 'middle_name' => 'G.'],
            ['first_name' => 'Jhon', 'surname' => 'Farillon', 'middle_name' => 'C.'],
            ['first_name' => 'Deann Samuel', 'surname' => 'Blanza', 'middle_name' => 'F.'],
            ['first_name' => 'Catherine Joy', 'surname' => 'Manzano', 'middle_name' => 'D.'],
            ['first_name' => 'Mitz', 'surname' => 'Ignacio', 'middle_name' => 'F.'],
            ['first_name' => 'John Kenedy', 'surname' => 'Quilang', 'middle_name' => 'B.'],
            ['first_name' => 'Shaine Paolo', 'surname' => 'Valdez', 'middle_name' => 'T.'],
            ['first_name' => 'Jilmar', 'surname' => 'Ferrer', 'middle_name' => 'F.'],
        ];

        foreach ($employees as $index => $emp) {
            DB::transaction(function () use ($emp, $department, $index) {
                $username = strtolower(str_replace(' ', '', $emp['first_name'] . $emp['surname']));
                $email = $username . '@gmail.com';

                $user = User::create([
                    'username' => $username,
                    'email'    => $email,
                    'password' => Hash::make('Password@123'),
                    'role'     => 'employee',
                ]);
                $employee = Employee::create([
                    'user_id'                        => $user->id,
                    'department_id'                  => $department->id,
                    'first_name'                     => $emp['first_name'],
                    'middle_name'                    => $emp['middle_name'],
                    'surname'                        => $emp['surname'],
                    'id_number'                      => 'LGU-2026-' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'birthdate'                      => '1995-01-01',
                    'place_of_birth'                 => 'Echague, Isabela',
                    'sex'                            => 'male',
                    'civil_status'                   => 'single',

                    // --- Added Physical Attributes ---
                    'height'                         => 170, // or '170 cm' depending on your column type
                    'weight'                         => 65,  // or '65 kg'
                    'bloodtype'                      => 'O+',

                    'highest_educational_attainment' => 'college',
                    'residential_address'            => 'Echague, Isabela',
                    'contact_number'                 => '09171234567',

                    // --- Added Gov IDs / System Numbers ---
                    'umid_id'                        => 'CRN-0111-1234567-8',
                    'pagibig_id'                     => '1234-5678-9012',
                    'philhealth_number'              => '12-345678901-2',
                    'psn_number'                     => '1234-5678-9012-3456', // PhilSys Card Number
                    'tin_number'                     => '123-456-789-000',

                    'employment_status'              => 'job_order',
                    'position'                       => 'Encoder',
                    'date_hired'                     => '2013-09-13',
                    'is_active'                      => true,
                ]);
                EmploymentHistory::create([
                    'employee_id'                  => $employee->id,
                    'previous_position'            => null,
                    'new_position'                 => $employee->position,
                    'previous_employment_status'   => null,
                    'new_employment_status'        => $employee->employment_status,
                    'effective_date'               => $employee->date_hired,
                    'remarks'                       => 'Initial employment record (seeded)',
                ]);
            });
        }
    }
}
