<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'username' => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'is_active' => true,
        ];
    }
}
