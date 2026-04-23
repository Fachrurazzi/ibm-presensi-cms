<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use App\Models\User;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
            'image' => null,
            'position_id' => Position::factory(),
            'join_date' => $this->faker->dateTimeBetween('-5 years', 'now'),
            'leave_quota' => 12,
            'cashable_leave' => $this->faker->numberBetween(0, 12),
            'is_default_password' => false,
            'face_model_path' => null,
            'remember_token' => null,
        ];
    }

    public function karyawan()
    {
        return $this->state(function (array $attributes) {
            return [];
        });
    }

    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'email' => 'admin@intiboga.com',
                'name' => 'Administrator',
            ];
        });
    }
}
