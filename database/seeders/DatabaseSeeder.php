<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class,
            LeaveConfigurationSeeder::class,
            LeaveCreditSeeder::class,
        ]);
    }
    //  // User::factory(10)->create();  
    //     User::factory()->create([
    //             'name' => 'Test User',
    //             'email' => 'test@example.com',
    //         ]);
}
