<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
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

    public function test_product_tax_breakdown_is_split_from_its_category_rate(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1200]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.taxRate', 18)
            ->assertJsonPath('data.basePrice', 1016.95)
            ->assertJsonPath('data.taxAmount', 183.05);
    }

    public function test_product_tax_breakdown_defaults_to_zero_for_untaxed_categories(): void
    {
        $category = Category::factory()->create(['tax_rate' => 0]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 100]);

        $this->getJson("/api/v1/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.taxRate', 0)
            ->assertJsonPath('data.basePrice', 100)
            ->assertJsonPath('data.taxAmount', 0);
    }

    public function test_creating_a_product_persists_the_new_billing_fields(): void
    {
        $category = Category::factory()->create();

        $payload = [
            'name' => 'Mobile Charger',
            'itemType' => 'GOODS',
            'price' => 1200,
            'stock' => 10,
            'categoryId' => $category->id,
            'sku' => 'CHG-001',
            'itemCode' => 'IC-9',
            'hsnSacCode' => '8504',
            'brand' => 'Acme',
            'unit' => 'PCS',
            'mrp' => 1500,
            'purchasePrice' => 900,
            'partNo' => 'PN-55',
        ];

        $response = $this->actingAsUser(User::factory()->admin()->create())
            ->postJson('/api/v1/products', $payload)
            ->assertOk();

        $response
            ->assertJsonPath('data.sku', 'CHG-001')
            ->assertJsonPath('data.itemCode', 'IC-9')
            ->assertJsonPath('data.hsnSacCode', '8504')
            ->assertJsonPath('data.brand', 'Acme')
            ->assertJsonPath('data.unit', 'PCS')
            ->assertJsonPath('data.mrp', '1500.00')
            ->assertJsonPath('data.purchasePrice', '900.00')
            ->assertJsonPath('data.partNo', 'PN-55');

        $this->assertDatabaseHas('products', ['sku' => 'CHG-001', 'hsn_sac_code' => '8504']);
    }

    public function test_product_sku_must_be_unique_but_can_keep_its_own_value_on_update(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Product::factory()->create(['sku' => 'DUP-1']);
        $product = Product::factory()->create(['sku' => 'DUP-1-OWN']);

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/products/{$product->id}", ['sku' => 'DUP-1'])
            ->assertStatus(422);

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/products/{$product->id}", ['sku' => 'DUP-1-OWN'])
            ->assertOk()
            ->assertJsonPath('data.sku', 'DUP-1-OWN');
    }

    public function test_creating_a_product_persists_tags(): void
    {
        $category = Category::factory()->create();

        $this->actingAsUser(User::factory()->admin()->create())
            ->postJson('/api/v1/products', [
                'name' => 'Combo Pack',
                'price' => 199,
                'stock' => 5,
                'categoryId' => $category->id,
                'tags' => ['BOGO', 'Combo'],
            ])
            ->assertOk()
            ->assertJsonPath('data.tags', ['BOGO', 'Combo']);

        $this->assertDatabaseHas('products', ['name' => 'Combo Pack']);
        $this->assertSame(['BOGO', 'Combo'], Product::where('name', 'Combo Pack')->firstOrFail()->tags);
    }

    public function test_updating_a_product_replaces_its_tags(): void
    {
        $product = Product::factory()->create(['tags' => ['BOGO']]);

        $this->actingAsUser(User::factory()->admin()->create())
            ->patchJson("/api/v1/products/{$product->id}", ['tags' => ['Festive']])
            ->assertOk()
            ->assertJsonPath('data.tags', ['Festive']);

        $this->assertSame(['Festive'], $product->fresh()->tags);
    }

    public function test_order_item_snapshots_the_tax_rate_at_order_time(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);
        $product = Product::factory()->create(['category_id' => $category->id, 'price' => 1200]);
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->postJson('/api/v1/orders', [
                'paymentMethod' => 'COD',
                'total' => 1200,
                'items' => [['productId' => $product->id, 'quantity' => 1]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'tax_rate' => 18]);

        // Changing the category's rate afterwards must not alter the already-placed order.
        $category->update(['tax_rate' => 28]);

        $order = Order::where('user_id', $user->id)->first();
        $this->getJson("/api/v1/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.items.0.basePrice', 1016.95)
            ->assertJsonPath('data.items.0.taxAmount', 183.05);

        $this->assertDatabaseHas('order_items', ['product_id' => $product->id, 'tax_rate' => 18]);
    }
}
