<?php
// database/factories/AttendancePermissionFactory.php

namespace Database\Factories;

use App\Models\AttendancePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendancePermissionFactory extends Factory
{
    protected $model = AttendancePermission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(['LATE', 'EARLY_LEAVE', 'BUSINESS_TRIP', 'SICK_WITH_CERT']),
            'date' => $this->faker->dateTimeBetween('-1 month', '+1 month')->format('Y-m-d'),
            'reason' => $this->faker->sentence(),
            'image_proof' => null,
            'status' => $this->faker->randomElement(['PENDING', 'APPROVED', 'REJECTED']),
        ];
    }
}
