<?php
// database/seeders/OfficesTableSeeder.php

namespace Database\Seeders;

use App\Models\Office;
use Illuminate\Database\Seeder;

class OfficesTableSeeder extends Seeder
{
    public function run(): void
    {
        $offices = [
            [
                'name' => 'Kantor Pusat Banjarmasin',
                'supervisor_name' => 'Bambang Supriyanto',
                'latitude' => -3.316694,
                'longitude' => 114.590111,
                'radius' => 100,
                // Hapus start_time dan end_time
            ],
            [
                'name' => 'Kantor Cabang Palangka Raya',
                'supervisor_name' => 'Siti Aminah',
                'latitude' => -2.213333,
                'longitude' => 113.916667,
                'radius' => 100,
            ],
            [
                'name' => 'Kantor Cabang Balikpapan',
                'supervisor_name' => 'Andi Wijaya',
                'latitude' => -1.237927,
                'longitude' => 116.852852,
                'radius' => 100,
            ],
        ];

        foreach ($offices as $office) {
            Office::firstOrCreate(['name' => $office['name']], $office);
        }
    }
}
