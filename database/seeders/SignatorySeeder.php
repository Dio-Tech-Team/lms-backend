<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Signatory;

class SignatorySeeder extends Seeder
{
    public function run(): void
    {
        $signatories = [
            ['role' => 'hr_officer', 'name' => 'FE A. BARTOLOME', 'position' => 'HR Officer'],
            ['role' => 'approving_authority', 'name' => 'FAUSTINO A. DY, V', 'position' => 'Municipal Mayor'],
        ];

        foreach ($signatories as $row) {
            Signatory::firstOrCreate(['role' => $row['role']], $row);
        }
    }
}
