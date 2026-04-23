<?php
// database/factories/LeaveFactory.php

namespace Database\Factories;

use App\Models\Leave;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $endDate = (clone $startDate)->modify('+' . rand(1, 5) . ' days');

        return [
            'user_id' => User::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['annual', 'sick', 'emergency', 'maternity', 'important']),
            'status' => $this->faker->randomElement(['PENDING', 'APPROVED', 'REJECTED']),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function approved()
    {
        return $this->state(function (array $attributes) {
            return ['status' => 'APPROVED'];
        });
    }

    public function pending()
    {
        return $this->state(function (array $attributes) {
            return ['status' => 'PENDING'];
        });
    }
}
