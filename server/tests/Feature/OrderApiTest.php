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

    public function test_a_customer_can_place_an_order_and_gets_an_order_number(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'discounted_price' => 80]);

        $response = $this->actingAsUser($customer)->postJson('/api/v1/orders', [
            'paymentMethod' => 'COD',
            'total' => 160,
            'items' => [['productId' => $product->id, 'quantity' => 2]],
        ])->assertOk()->assertJsonPath('message', 'Order created successfully');

        $this->assertSame(100001, $response->json('data.orderNumber'));
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
