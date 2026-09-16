<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'PENDING',
            'payment_method' => 'COD',
            'total' => fake()->randomFloat(2, 50, 800),
            'address_id' => null,
        ];
    }
}
