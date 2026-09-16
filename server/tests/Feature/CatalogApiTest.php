<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_are_public_and_camel_cased(): void
    {
        $product = Product::factory()->create(['discounted_price' => 40, 'sort_order' => 1]);

        $this->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.0.id', $product->id)
            ->assertJsonPath('data.0.discountedPrice', '40.00')
            ->assertJsonPath('data.0.sortOrder', 1)
            ->assertJsonPath('data.0.category.name', $product->category->name);
    }

    public function test_creating_a_product_requires_an_admin(): void
    {
        $payload = ['name' => 'X', 'price' => 10, 'stock' => 5, 'category' => Category::factory()->create()->slug];

        $this->postJson('/api/v1/products', $payload)->assertUnauthorized();

        $this->actingAsUser(User::factory()->create())
            ->postJson('/api/v1/products', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'Admin access required');

        $this->actingAsUser(User::factory()->admin()->create())
            ->postJson('/api/v1/products', $payload)
            ->assertOk()
            ->assertJsonPath('data.name', 'X');

        $this->assertDatabaseHas('products', ['name' => 'X']);
    }

    public function test_deleting_a_product_soft_deletes_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAsUser(User::factory()->admin()->create())
            ->deleteJson("/api/v1/products/{$product->id}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');
    }

    public function test_category_status_toggle(): void
    {
        $category = Category::factory()->create(['is_active' => true]);

        $this->actingAsUser(User::factory()->admin()->create())
            ->patchJson("/api/v1/categories/{$category->id}/status", ['isActive' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Category deactivated successfully')
            ->assertJsonPath('data.isActive', false);
    }
}
