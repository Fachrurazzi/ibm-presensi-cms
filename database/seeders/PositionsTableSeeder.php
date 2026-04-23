<?php
// database/seeders/PositionsTableSeeder.php

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $positions = [
            'Direktur Utama',
            'General Manager',
            'Manager Operasional',
            'Supervisor',
            'Staff Administrasi',
            'Staff HRD',
            'Staff Keuangan',
            'Staff IT',
            'Staff Marketing',
            'Karyawan Lapangan',
        ];

        foreach ($positions as $position) {
            // Gunakan firstOrCreate untuk menghindari duplikasi
            Position::firstOrCreate(['name' => $position]);
        }
    }
}
