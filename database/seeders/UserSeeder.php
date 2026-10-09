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
        $admin = config('app.seed_admin');

        if (!empty($admin['username']) && !empty($admin['password'])) {
            User::updateOrCreate(
                ['username' => $admin['username']],
                [
                    'email'                => $admin['email'],
                    'password'             => Hash::make($admin['password']),
                    'role'                 => 'super_admin',
                    'must_change_password' => true,
                ]
            );
        }
    }
}
