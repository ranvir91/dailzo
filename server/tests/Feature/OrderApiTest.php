<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_place_an_order_and_the_order_is_identified_by_its_id(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'discounted_price' => 80]);

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 160,
            'items' => [['productId' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('message', 'Order created successfully');

        // No separate order_number column anymore — the primary key id is
        // the order number everywhere (API, Atlas, Octa, both Flutter apps).
        $this->assertIsInt($response->json('data.id'));
        $this->assertArrayNotHasKey('orderNumber', $response->json('data'));
        $this->assertSame('80.00', $response->json('data.items.0.price'));
    }

    public function test_order_total_below_the_enforced_minimum_is_rejected(): void
    {
        StoreSetting::current()->update(['min_order_value' => 199, 'min_order_value_enabled' => true]);

        $customer = User::factory()->create();
        // The minimum-order check runs against the real cart subtotal (price
        // x quantity from the DB), not the client-sent `total` — a client
        // can no longer dodge it by just sending a smaller total.
        $product = Product::factory()->create(['price' => 50, 'discounted_price' => null]);

        $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 500,
            'items' => [['productId' => $product->id, 'quantity' => 1]],
        ])->assertStatus(400)->assertJsonPath('success', false);
    }

    private function freebieProduct(array $overrides = []): Product
    {
        return Product::factory()->create(array_merge([
            'price' => 200,
            'discounted_price' => null,
            'is_freebie' => true,
            'freebie_price' => 1,
            'freebie_min_order_value' => 500,
        ], $overrides));
    }

    public function test_a_freebie_product_is_charged_the_freebie_price_once_its_threshold_is_met(): void
    {
        $customer = User::factory()->create();
        $regular = Product::factory()->create(['price' => 600, 'discounted_price' => null]);
        $freebie = $this->freebieProduct();

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 601,
            'items' => [
                ['productId' => $regular->id, 'quantity' => 1],
                ['productId' => $freebie->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $items = collect($response->json('data.items'));
        $this->assertSame('600.00', $items->firstWhere('productId', $regular->id)['price']);
        $this->assertSame('1.00', $items->firstWhere('productId', $freebie->id)['price']);
        // MRP is still snapshotted at the real price, so the invoice can show
        // the full discount even though only Rs.1 was charged.
        $this->assertSame('200.00', $items->firstWhere('productId', $freebie->id)['mrp']);
    }

    public function test_a_freebie_product_is_charged_normally_when_its_threshold_is_not_met(): void
    {
        $customer = User::factory()->create();
        $regular = Product::factory()->create(['price' => 100, 'discounted_price' => null]);
        $freebie = $this->freebieProduct();

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 300,
            'items' => [
                ['productId' => $regular->id, 'quantity' => 1],
                ['productId' => $freebie->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $items = collect($response->json('data.items'));
        $this->assertSame('200.00', $items->firstWhere('productId', $freebie->id)['price']);
    }

    public function test_only_the_store_configured_quantity_of_freebies_is_charged_the_freebie_price(): void
    {
        StoreSetting::current()->update(['max_freebie_quantity_per_order' => 1]);
        $customer = User::factory()->create();
        $regular = Product::factory()->create(['price' => 600, 'discounted_price' => null]);
        $freebie = $this->freebieProduct();

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 602,
            'items' => [
                ['productId' => $regular->id, 'quantity' => 1],
                ['productId' => $freebie->id, 'quantity' => 2],
            ],
        ])->assertOk();

        // 2 units requested but the store cap is 1 — the whole line (not a
        // partial split) reverts to normal pricing.
        $items = collect($response->json('data.items'));
        $this->assertSame('200.00', $items->firstWhere('productId', $freebie->id)['price']);
    }

    public function test_the_global_freebie_cap_applies_across_different_freebie_products_not_per_product(): void
    {
        // The bug this fixes: two DIFFERENT freebie products, cap of 1 total
        // — previously each product was capped independently, so a customer
        // could add 1 of each (2 freebies) when only 1 total was intended.
        StoreSetting::current()->update(['max_freebie_quantity_per_order' => 1]);
        $customer = User::factory()->create();
        $regular = Product::factory()->create(['price' => 600, 'discounted_price' => null]);
        $freebieA = $this->freebieProduct(['price' => 150]);
        $freebieB = $this->freebieProduct(['price' => 250]);

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 602,
            'items' => [
                ['productId' => $regular->id, 'quantity' => 1],
                ['productId' => $freebieA->id, 'quantity' => 1],
                ['productId' => $freebieB->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $items = collect($response->json('data.items'));
        $prices = [
            $items->firstWhere('productId', $freebieA->id)['price'],
            $items->firstWhere('productId', $freebieB->id)['price'],
        ];
        // Exactly one of the two got the freebie price, not both.
        $this->assertCount(1, array_filter($prices, fn ($p) => $p === '1.00'));
        $this->assertCount(1, array_filter($prices, fn ($p) => $p !== '1.00'));
    }

    public function test_a_freebie_product_is_charged_normally_when_is_freebie_is_off(): void
    {
        $customer = User::factory()->create();
        $regular = Product::factory()->create(['price' => 600, 'discounted_price' => null]);
        $freebie = $this->freebieProduct(['is_freebie' => false]);

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 800,
            'items' => [
                ['productId' => $regular->id, 'quantity' => 1],
                ['productId' => $freebie->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $items = collect($response->json('data.items'));
        $this->assertSame('200.00', $items->firstWhere('productId', $freebie->id)['price']);
    }

    public function test_a_freebie_products_own_price_does_not_count_toward_its_own_threshold(): void
    {
        $customer = User::factory()->create();
        // Only the freebie product itself in the cart — its own price must
        // not count toward unlocking its own threshold.
        $freebie = $this->freebieProduct(['price' => 600]);

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 600,
            'items' => [
                ['productId' => $freebie->id, 'quantity' => 1],
            ],
        ])->assertOk();

        $items = collect($response->json('data.items'));
        $this->assertSame('600.00', $items->firstWhere('productId', $freebie->id)['price']);
    }

    public function test_delivery_charges_are_snapshotted_at_order_time_not_recomputed_later(): void
    {
        StoreSetting::current()->update(['delivery_charges' => 49]);
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'discounted_price' => null]);

        $order = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 1,
            'items' => [['productId' => $product->id, 'quantity' => 1]],
        ])->assertOk()->json('data');

        $this->assertSame('49.00', $order['deliveryCharges']);
        $this->assertSame('149.00', $order['total']);

        // Store-wide delivery charge changes later must not retroactively
        // change what an already-placed order shows it was charged.
        StoreSetting::current()->update(['delivery_charges' => 99]);
        $this->assertSame('49.00', Order::find($order['id'])->fresh()->delivery_charges);
    }

    public function test_customers_only_see_their_own_orders_but_admins_see_all(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $product = Product::factory()->create();

        foreach ([$a, $b] as $u) {
            $this->actingAsUser($u)->postJson('/api/v1/orders', [
                'paymentMethod' => 'COD', 'total' => 10,
                'items' => [['productId' => $product->id, 'quantity' => 1]],
            ])->assertOk();
        }

        $this->actingAsUser($a)->getJson('/api/v1/orders')->assertJsonCount(1, 'data');
        $this->actingAsUser(User::factory()->admin()->create())->getJson('/api/v1/orders')->assertJsonCount(2, 'data');
    }

    public function test_status_transitions_follow_the_state_machine(): void
    {
        $order = Order::factory()->create(['status' => 'PENDING']);
        $admin = User::factory()->admin()->create();

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'DELIVERED'])
            ->assertOk()
            ->assertJsonPath('message', 'Invalid order status transition');

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/orders/{$order->id}/status", ['status' => 'PAYMENT_PENDING'])
            ->assertOk()
            ->assertJsonPath('data.status', 'PAYMENT_PENDING');
    }
}
