<?php
// database/seeders/UsersTableSeeder.php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Position;
use App\Models\Office;
use App\Models\Shift;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UsersTableSeeder extends Seeder
{
    public function run(): void
    {
        // Admin User
        $adminPosition = Position::where('name', 'Supervisor')->first() ?? Position::first();

        User::updateOrCreate(
            ['email' => 'admin@intiboga.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'position_id' => $adminPosition?->id,
                'join_date' => now(),
                'leave_quota' => 12,
                'cashable_leave' => 0,
                'is_default_password' => false,
                'email_verified_at' => now(),
            ]
        );

        // Sample Employees
        $karyawanPosition = Position::where('name', 'Karyawan Lapangan')->first() ?? Position::first();
        $defaultOffice = Office::first();
        $defaultShift = Shift::first();

        $employees = [
            ['name' => 'Budi Santoso', 'email' => 'budi@intiboga.com'],
            ['name' => 'Siti Nurhaliza', 'email' => 'siti@intiboga.com'],
            ['name' => 'Ahmad Rizki', 'email' => 'ahmad@intiboga.com'],
            ['name' => 'Dewi Kartika', 'email' => 'dewi@intiboga.com'],
            ['name' => 'Eko Prasetyo', 'email' => 'eko@intiboga.com'],
        ];

        foreach ($employees as $employee) {
            $user = User::updateOrCreate(
                ['email' => $employee['email']],
                [
                    'name' => $employee['name'],
                    'password' => Hash::make('password'),
                    'position_id' => $karyawanPosition->id,
                    'join_date' => now()->subYears(rand(1, 5)),
                    'leave_quota' => 12,
                    'cashable_leave' => rand(0, 5),
                    'is_default_password' => true,
                    'email_verified_at' => now(),
                ]
            );

            // ========== SOLUSI TERBAIK: withoutEvents() ==========
            if ($defaultOffice && $defaultShift) {
                Schedule::withoutEvents(function () use ($user, $defaultShift, $defaultOffice) {
                    // Schedule permanen (berlaku terus)
                    Schedule::updateOrCreate(
                        [
                            'user_id' => $user->id,
                            'start_date' => $user->join_date->format('Y-m-d'),
                            'end_date' => null,
                        ],
                        [
                            'shift_id' => $defaultShift->id,
                            'office_id' => $defaultOffice->id,
                            'is_wfa' => false,
                            'is_banned' => false,
                        ]
                    );
                });

                // Optional: Schedule untuk rolling area (mutasi sementara)
                if (rand(1, 100) <= 20) {
                    $otherOffice = Office::where('id', '!=', $defaultOffice->id)->first();
                    if ($otherOffice) {
                        Schedule::withoutEvents(function () use ($user, $defaultShift, $otherOffice) {
                            Schedule::create([
                                'user_id' => $user->id,
                                'shift_id' => $defaultShift->id,
                                'office_id' => $otherOffice->id,
                                'start_date' => Carbon::now()->addDays(rand(30, 60))->format('Y-m-d'),
                                'end_date' => Carbon::now()->addDays(rand(61, 90))->format('Y-m-d'),
                                'is_wfa' => false,
                                'is_banned' => false,
                            ]);
                        });
                    }
                }
            }
        }

        $this->command->info('Users seeded successfully!');
    }
}
