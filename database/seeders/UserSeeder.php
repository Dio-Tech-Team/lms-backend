<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'leavesyncadmin1'],
            [
                'email'    => 'leavesyncadmin1@gmail.com',
                'password' => Hash::make('@Password123'),
                'role'     => 'super_admin',
                'must_change_password' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'leavesyncadmin2'],
            [
                'email'    => 'leavesyncadmin2@gmail.com',
                'password' => Hash::make('@Password123'),
                'role'     => 'super_admin',
                'must_change_password' => true,
            ]
        );
        User::updateOrCreate(
            ['username' => 'hradmin'],
            [
                'email' => 'leavesynchr@gmail.com',
                'password' => Hash::make('@Password123'),
                'role' => 'hr_admin',
                'must_change_password' => true,
            ]

        );
        $employees = [
            ['Sammy', 'Cruz'],
            ['Althea', 'De Leon'],
            ['Jhonny', 'Aquino'],
            ['Carmela', 'Manzano'],
            ['Danilo', 'Espiritu'],

            ['Maria', 'Bautista'],
            ['Ramon', 'Perez'],
            ['Rowena', 'Dela Cruz'],
            ['Eduardo', 'Panganiban'],
            ['Angelita', 'Dizon'],
            ['Jose', 'Ocampo'],
            ['Carmela', 'Tolentino'],
            ['Danilo', 'Ramos'],
            ['Rowelson', 'Garcia'],
            ['Eduardo', 'Manalili'],

            ['Catherine', 'Manzano'],
            ['Kenedy', 'Quilang'],
            ['Reynaldo', 'Tomas'],
            ['Jhon', 'Farillon'],
            ['Mitz', 'Ignacio'],

            ['Shane Paolo', 'Valdez'],
            ['Jilmar', 'Ferrer'],
            ['Jamby', 'Villarta'],
            ['Rolando', 'Gonzales'],
            ['Arnel', 'Mercado'],

            ['Jen', 'Tuquib'],
            ['Jhon', 'Reyes'],
            ['Jayson', 'Espiritu'],
            ['Joy', 'Dizon'],
            ['Sam', 'Blanza'],
            ['Nick', 'Pascual'],

            ['Leonardo', 'Meneses'],
            ['Mark', 'Soriano'],
            ['Mark', 'Rama'],
            ['Kiel', 'Ramon'],
        ];

        foreach ($employees as $employee) {

            $username = strtolower(
                str_replace(' ', '', $employee[0] . $employee[1])
            );

            // User::create([
            //     'username' => $username,
            //     'email' => $username . '@gmail.com',
            //     'password' => Hash::make('@Password123'),
            //     'role' => 'employee'
            // ]);
            User::updateOrCreate(
                ['username' => $username],
                [
                    'email' => $username . '@example.com',
                    'password' => Hash::make('@Password123'),
                    'role' => 'employee',
                    'must_change_password' => true,
                ]
            );
        }
    }
}
