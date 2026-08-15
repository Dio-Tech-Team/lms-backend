<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Administrative Aide I',
            'Administrative Aide III',
            'Administrative Assistant I',
            'Administrative Officer I',
            'HR Officer I',
            // ...rest of the plantilla list
        ];

        foreach ($positions as $title) {
            Position::firstOrCreate(['title' => $title]);
        }
    }
}
