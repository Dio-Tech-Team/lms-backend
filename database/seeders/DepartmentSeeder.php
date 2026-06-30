<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['name' => 'Human Resource', 'code' => "HR", 'is_active' => true],
            ['name' => 'Finance', 'code' => 'FIN', 'is_active' => true],
            ['name' => 'Information Technology', 'code' => 'IT', 'is_active' => true],
            ['name' => 'Health', 'code' => 'HLT', 'is_active' => true],
            ['name' => 'Social Welfare', 'code' => 'SWD', 'is_active' => true],
            ['name' => 'Municipal Agriculture Services Office', 'code' => 'MASO', 'is_active' => true],
            ['name' => 'Mayor Office', 'code' => 'MO', 'is_active' => true],
        ];

        foreach ($departments as $department) {
            Department::create($department);
        }
    }
}
