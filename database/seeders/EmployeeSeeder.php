<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Department;
use App\Models\EmploymentHistory;
use App\Models\User;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        // Solid pools of local names
        $firstNames = [
            'Juan',
            'Maria',
            'Jose',
            'Rowena',
            'Danilo',
            'Angelita',
            'Reynaldo',
            'Elena',
            'Edgardo',
            'Fe',
            'Rolando',
            'Grace',
            'Arnel',
            'Christina',
            'Jayson',
            'Joy',
            'Christian',
            'Michelle',
            'Mark',
            'Glenda',
            'Noel',
            'Althea',
            'Ramon',
            'Carmela',
            'Eduardo',
            'Liezel',
            'Rodel',
            'Maricel',
            'Randy',
            'Jovita',
            'Renato',
            'Nerissa',
            'Jeffrey',
            'Rhea',
            'Jhon',
            'Princess',
            'Manuel',
            'Divina',
            'Antonio',
            'Liza'
        ];

        $middleLetters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'R', 'S', 'T', 'V'];

        $lastNames = [
            'Santos',
            'Reyes',
            'Cruz',
            'Bautista',
            'Ocampo',
            'Garcia',
            'Mendoza',
            'Torres',
            'Tomas',
            'Aquino',
            'Dela Cruz',
            'Ramos',
            'Gonzales',
            'Villanueva',
            'Mercado',
            'Castro',
            'Espiritu',
            'Dizon',
            'Corpuz',
            'Pascual',
            'Santiago',
            'Soriano',
            'Valenzuela',
            'De Leon',
            'Perez',
            'Tolentino',
            'Manalili',
            'Canceran',
            'Farillon',
            'Blanza',
            'Manzano',
            'Panganiban',
            'Del Rosario',
            'Castillo',
            'Guzman'
        ];
        $barangays = [
            'Sinabbaran',
            'Ipil',
            'Soyung',
            'Pangallaoan',
            'Maligaya',
            'Silauan Sur',
            'Silauan Norte',
            'Annafunan',
            'Garay',
            'Babaran'
        ];

        $departments = Department::all();

        if ($departments->isEmpty()) {
            $this->command->error('Please seed your departments table first before running the EmployeeSeeder!');
            return;
        }

        $deptArray = $departments->toArray();

        // Exact loop generation cap of 250 records
        for ($i = 0; $i < 250; $i++) {

            $fName = $firstNames[$i % count($firstNames)];
            $mName = $middleLetters[($i + 1) % count($middleLetters)];
            $lName = $lastNames[($i + 2) % count($lastNames)];

            $selectedDept = $deptArray[$i % count($deptArray)];
            $selectedBarangay = $barangays[$i % count($barangays)];

            // 1. Generate the clean username and email directly from the deterministic names
            $cleanFirst = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $fName));
            $cleanSurname = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $lName));
            $username = $cleanFirst . $cleanSurname;

            // 2. Explicitly create the User account first to pass clean credentials
            $user = User::factory()->create([
                'username' => $username,
                'email'    => $username . '@gmail.com',
            ]);

            // 3. Create the employee using the newly created user_id override
            $employee = Employee::factory()->create([
                'user_id'       => $user->id,
                'first_name'    => $fName,
                'middle_name'   => $mName,
                'surname'       => $lName,
                'department_id' => $selectedDept['id'],
                'place_of_birth'      => $selectedBarangay . ', Echague, Isabela',
                'residential_address' => $selectedBarangay . ', Echague, Isabela',
            ]);

            // 4. Synchronize employment history log
            EmploymentHistory::create([
                'employee_id'                => $employee->id,
                'previous_position'          => null,
                'new_position'               => $employee->position,
                'previous_employment_status' => null,
                'new_employment_status'      => $employee->employment_status,
                'effective_date'             => $employee->date_hired,
                'remarks'                    => 'Initial employment record',
            ]);
        }
    }
}
