<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['code' => 'GSO',     'name' => 'General Services Office'],
            ['code' => 'MACO',    'name' => 'Municipal Accounting Office'],
            ['code' => 'MADO',    'name' => 'Municipal Administrator Office'],
            ['code' => 'MAGO',    'name' => 'Municipal Agriculture Office'],
            ['code' => 'MARKET',  'name' => 'Municipal Market Operations'],
            ['code' => 'MASO',    'name' => 'Municipal Agricultural Services Office'],
            ['code' => 'MBO',     'name' => 'Municipal Budget Office'],
            ['code' => 'MBPLO',   'name' => 'Municipal Business Permits and Licensing Office'],
            ['code' => 'MCRO',    'name' => 'Municipal Civil Registrar Office'],
            ['code' => 'MDRRMO',  'name' => 'Municipal Disaster Risk Reduction and Management Office'],
            ['code' => 'MEO',     'name' => 'Municipal Engineering Office'],
            ['code' => 'MENRO',   'name' => 'Municipal Environment and Natural Resources Office'],
            ['code' => 'MHO',     'name' => 'Municipal Health Office'],
            ['code' => 'MHRMO',   'name' => 'Municipal Human Resource Management Office'],
            ['code' => 'MPDO',    'name' => 'Municipal Planning and Development Office'],
            ['code' => 'MSWDO',   'name' => 'Municipal Social Welfare and Development Office'],
            ['code' => 'MTCO',     'name' => 'Municipal Trial Court Office'],
            ['code' => 'MTO',     'name' => 'Municipal Treasurer Office'],
            ['code' => 'MO',      'name' => "Mayor's Office"],
            ['code' => 'POSU',    'name' => 'Public Order and Safety Unit'],
            ['code' => 'VMSBO',   'name' => 'Vice Mayor / Sangguniang Bayan Office'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name'], 'is_active' => true]
            );
        }
    }
}
