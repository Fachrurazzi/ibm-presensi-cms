<?php
// database/factories/AttendanceFactory.php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $schedule = Schedule::inRandomOrder()->first() ?? Schedule::factory();
        $user = $schedule->user ?? User::factory();
        $startTime = Carbon::parse($schedule->shift->start_time);
        $isLate = $this->faker->boolean(20);

        if ($isLate) {
            $actualStart = $startTime->copy()->addMinutes($this->faker->numberBetween(1, 60));
        } else {
            $actualStart = $startTime->copy();
        }

        $endTime = Carbon::parse($schedule->shift->end_time);
        $actualEnd = $endTime->copy()->addMinutes($this->faker->numberBetween(-30, 30));

        return [
            'user_id' => $user->id,
            'schedule_id' => $schedule->id,
            'attendance_permission_id' => null,
            'schedule_latitude' => $schedule->office->latitude,
            'schedule_longitude' => $schedule->office->longitude,
            'schedule_start_time' => $startTime,
            'schedule_end_time' => $endTime,
            'start_latitude' => $schedule->office->latitude,
            'start_longitude' => $schedule->office->longitude,
            'end_latitude' => $schedule->office->latitude,
            'end_longitude' => $schedule->office->longitude,
            'start_time' => $actualStart,
            'end_time' => $actualEnd,
            'created_at' => $actualStart,
            'updated_at' => $actualEnd,
        ];
    }

    public function late()
    {
        return $this->state(function (array $attributes) {
            $schedule = Schedule::find($attributes['schedule_id']);
            $startTime = Carbon::parse($schedule->shift->start_time);

            return [
                'start_time' => $startTime->addMinutes(rand(1, 120)),
            ];
        });
    }
}
