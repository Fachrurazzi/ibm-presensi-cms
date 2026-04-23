<?php
// database/factories/ScheduleFactory.php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\User;
use App\Models\Shift;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+3 month');
        $hasEndDate = $this->faker->boolean(30); // 30% schedule memiliki end_date

        return [
            'user_id' => User::factory(),
            'shift_id' => Shift::factory(),
            'office_id' => Office::factory(),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $hasEndDate
                ? Carbon::parse($startDate)->addDays($this->faker->numberBetween(7, 90))->format('Y-m-d')
                : null,
            'is_wfa' => $this->faker->boolean(10),
            'is_banned' => false,
            'banned_reason' => null,
        ];
    }

    /**
     * State untuk schedule permanen (tanpa end_date)
     */
    public function permanent()
    {
        return $this->state(function (array $attributes) {
            return [
                'end_date' => null,
            ];
        });
    }

    /**
     * State untuk schedule yang sudah berakhir
     */
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'start_date' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'end_date' => Carbon::now()->subDays(1)->format('Y-m-d'),
            ];
        });
    }

    /**
     * State untuk schedule aktif saat ini
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'start_date' => Carbon::now()->subDays(rand(1, 30))->format('Y-m-d'),
                'end_date' => null,
            ];
        });
    }
}
