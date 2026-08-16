<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [

            // Casual And Permanent Positions
            'Administrative Aide I',
            'Administrative Aide II',
            'Administrative Aide III',
            'Administrative Assistant I',
            'Administrative Assistant II',
            'Administrative Assistant III',
            'Administrative Officer I',
            'Administrative Officer II',
            'Administrative Officer III',
            'HRMO I',
            'HRMO II',
            'HRMO III',

            // Job Order Positions
            'Encoder',

            //Elected Officials
            'Sanguniang Bayan',
            'Municipal Mayor',
            'Municipal Vice Mayor',
            // ...rest of the plantilla list
        ];

        foreach ($positions as $title) {
            Position::firstOrCreate(['title' => $title]);
        }
    }
}
