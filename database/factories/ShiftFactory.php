<?php
// database/factories/ShiftFactory.php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Shift Pagi',
                'Shift Siang',
                'Shift Malam',
                'Shift Kalsel',
                'Shift Kalteng'
            ]),
            'start_time' => $this->faker->randomElement(['08:30:00', '07:30:00', '13:00:00', '22:00:00']),
            'end_time' => $this->faker->randomElement(['17:30:00', '16:30:00', '21:00:00', '06:00:00']),
            'description' => $this->faker->sentence(),
        ];
    }
}
