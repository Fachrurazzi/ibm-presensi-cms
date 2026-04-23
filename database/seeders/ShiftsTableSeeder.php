<?php
// database/seeders/ShiftsTableSeeder.php

namespace Database\Seeders;

use App\Models\Shift;
use Illuminate\Database\Seeder;

class ShiftsTableSeeder extends Seeder
{
    public function run(): void
    {
        $shifts = [
            [
                'name' => 'Shift Kalimantan Selatan',
                'start_time' => '08:30:00',
                'end_time' => '17:30:00',
                'description' => 'Jam kerja standar Kalimantan Selatan (WITA)',
            ],
            [
                'name' => 'Shift Kalimantan Tengah',
                'start_time' => '07:30:00',
                'end_time' => '16:30:00',
                'description' => 'Jam kerja standar Kalimantan Tengah (WIB)',
            ],
            [
                'name' => 'Shift Pagi',
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'description' => 'Shift pagi standar',
            ],
        ];

        foreach ($shifts as $shift) {
            Shift::firstOrCreate(['name' => $shift['name']], $shift);
        }
    }
}
