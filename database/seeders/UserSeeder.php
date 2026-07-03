<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'username' => 'hradmin',
            'email'    => 'hradmin@gmail.com',
            'password' => Hash::make('@Password123'),
            'role'     => 'hr_admin',
        ]);

        $sam = User::create([
            'username' => 'sam blanza',
            'email'    => 'samblanza@gmail.com',
            'password' => Hash::make('@Password123'),
            'role'     => 'employee',
        ]);

        Employee::create([
            'user_id'           => $sam->id,
            'department_id'     => Department::where('code', 'IT')->first()->id,
            'first_name'        => 'Sam',
            'middle_name'       => null,
            'last_name'         => 'Blanza',
            'birthdate'         => '1995-01-01',
            'contact_number'    => '09000000000',
            'id_number'         => 'EMP-001',
            'employment_status' => 'permanent',
            'position'          => 'Staff',
            'date_hired'        => '2024-01-01',
        ]);
    }
}