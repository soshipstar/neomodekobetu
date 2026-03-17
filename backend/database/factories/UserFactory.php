<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'classroom_id' => 1,
            'username' => fake()->unique()->userName(),
            'password' => Hash::make('password'),
            'full_name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'user_type' => 'staff',
            'is_master' => false,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'admin',
        ]);
    }

    public function staff(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'staff',
        ]);
    }

    public function guardian(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'guardian',
        ]);
    }
}
