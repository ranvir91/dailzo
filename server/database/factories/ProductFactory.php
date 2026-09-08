<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'category_id' => Category::factory(),
            'price' => fake()->randomFloat(2, 20, 500),
            'discounted_price' => null,
            'stock' => fake()->numberBetween(0, 100),
            'reserved' => 0,
            'images' => [],
            'sort_order' => 0,
        ];
    }
}
