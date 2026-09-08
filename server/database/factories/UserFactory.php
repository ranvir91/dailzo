<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => (string) fake()->unique()->numerify('9#########'),
            'role' => 'CUSTOMER',
            'password' => 'password',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'ADMIN']);
    }
}
