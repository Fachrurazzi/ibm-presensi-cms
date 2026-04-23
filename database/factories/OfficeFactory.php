<?php
// database/factories/OfficeFactory.php

namespace Database\Factories;

use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

class OfficeFactory extends Factory
{
    protected $model = Office::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company() . ' - ' . $this->faker->city(),
            'supervisor_name' => $this->faker->name(),
            'latitude' => $this->faker->latitude(-3.5, -2.5),
            'longitude' => $this->faker->longitude(114, 115),
            'radius' => $this->faker->numberBetween(50, 200),
            'start_time' => '08:30:00',
            'end_time' => '17:30:00',
        ];
    }
}
