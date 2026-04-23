<?php
// database/seeders/DatabaseSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Urutan seeder yang benar
        $this->call([
            PositionsTableSeeder::class,        // 1. Buat positions dulu
            OfficesTableSeeder::class,          // 2. Buat offices
            ShiftsTableSeeder::class,           // 3. Buat shifts
            UsersTableSeeder::class,            // 4. Buat users (membutuhkan position_id)
            RolesAndPermissionsSeeder::class,   // 5. Assign roles
        ]);

        // Factory untuk dummy data (opsional, hanya jika dibutuhkan)
        // Pastikan factory TIDAK membuat positions baru
        if (app()->environment('local') && false) { // Set false untuk nonaktifkan
            \App\Models\Attendance::factory(50)->create();
            \App\Models\Leave::factory(20)->create();
            \App\Models\AttendancePermission::factory(15)->create();
        }
    }
}
