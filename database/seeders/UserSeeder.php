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
        // User::create([
        //     'username' => 'hradmin',
        //     'email' => 'hradmin@gmail.com',
        //     'password' => Hash::make('@Password123'),
        //     'role' => 'hr_admin'
        // ]);
        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'email'    => 'superadmin@gmail.com',
                'password' => Hash::make('@Password123'),
                'role'     => 'super_admin',
                'must_change_password' => true,
            ]
        );

        User::updateOrCreate(
            ['username' => 'hradmin'],
            [
                'email' => 'hradmin@gmail.com',
                'password' => Hash::make('@Password123'),
                'role' => 'hr_admin',
                'must_change_password' => false,
                // 'email_verified_at' => now() // Add this line
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

            ['Joel', 'Pastor'],
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
                    'email' => $username . '@gmail.com',
                    'password' => Hash::make('@Password123'),
                    'role' => 'employee',
                    'must_change_password' => false,
                ]
            );
        }
    }
}
