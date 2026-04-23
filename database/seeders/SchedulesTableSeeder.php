<?php
// database/seeders/SchedulesTableSeeder.php

namespace Database\Seeders;

use App\Models\Schedule;
use App\Models\User;
use App\Models\Shift;
use App\Models\Office;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SchedulesTableSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::role('karyawan')->get();
        $shifts = Shift::all();
        $offices = Office::all();

        if ($users->isEmpty()) {
            $this->command->warn('Tidak ada user dengan role karyawan. Seeder dihentikan.');
            return;
        }

        if ($shifts->isEmpty()) {
            $this->command->warn('Tidak ada data shift. Seeder dihentikan.');
            return;
        }

        if ($offices->isEmpty()) {
            $this->command->warn('Tidak ada data office. Seeder dihentikan.');
            return;
        }

        $this->command->info('Membuat schedule untuk ' . $users->count() . ' karyawan...');

        foreach ($users as $user) {
            // Schedule permanen (berlaku terus)
            Schedule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'start_date' => Carbon::now()->subDays(rand(0, 30))->format('Y-m-d'),
                    'end_date' => null,
                ],
                [
                    'shift_id' => $shifts->random()->id,
                    'office_id' => $offices->random()->id,
                    'is_wfa' => rand(1, 100) <= 10,
                    'is_banned' => false,
                ]
            );

            // Optional: Schedule untuk periode tertentu (rolling area)
            if (rand(1, 100) <= 30) {
                $startDate = Carbon::now()->addDays(rand(30, 60));
                $endDate = $startDate->copy()->addDays(rand(7, 30));

                Schedule::create([
                    'user_id' => $user->id,
                    'shift_id' => $shifts->random()->id,
                    'office_id' => $offices->random()->id,
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'is_wfa' => rand(1, 100) <= 10,
                    'is_banned' => false,
                ]);
            }
        }

        $this->command->info('Schedule berhasil dibuat!');
    }
}
