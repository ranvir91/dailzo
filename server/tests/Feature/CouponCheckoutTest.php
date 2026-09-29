<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Coupons used to be applied entirely client-side: the app computed a
 * discount and sent the final `total`, and nothing server-side recorded
 * which coupon an order used — so per_user_limit, usage_limit,
 * min_order_value etc. were never actually enforced. These tests cover the
 * checkout-time enforcement added in OrderController::store /
 * CouponService::resolveForCheckout.
 */
class CouponCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function placeOrder(User $user, Product $product, ?string $couponCode = null, int $quantity = 1)
    {
        $payload = [
            'paymentMethod' => 'COD',
            'total' => 1, // ignored server-side; present only for older-client compatibility
            'items' => [['productId' => $product->id, 'quantity' => $quantity]],
        ];
        if ($couponCode !== null) {
            $payload['couponCode'] = $couponCode;
        }

        return $this->actingAsUser($user)->postJson('/api/v1/orders', $payload);
    }

    public function test_a_valid_coupon_discounts_the_server_computed_total(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'FLAT100', 'type' => 'FIXED', 'discount' => 100, 'min_order_value' => 0]);

        $response = $this->placeOrder($user, $product, 'FLAT100')->assertOk();

        // subtotal 500 + delivery_charges default 29 - discount 100 = 429
        $this->assertSame('429.00', $response->json('data.total'));
        $this->assertSame('100.00', $response->json('data.discountAmount'));
        $this->assertSame('29.00', $response->json('data.deliveryCharges'));
        $this->assertSame('FLAT100', $response->json('data.coupon.code'));
        $this->assertDatabaseHas('coupon_usages', ['user_id' => $user->id]);
    }

    public function test_a_coupon_cannot_be_used_more_than_its_per_user_limit(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100, 'per_user_limit' => 2]);

        $this->placeOrder($user, $product, 'FLAT100')->assertOk();
        $this->placeOrder($user, $product, 'FLAT100')->assertOk();

        // Third redemption by the same user must be rejected...
        $this->placeOrder($user, $product, 'FLAT100')
            ->assertStatus(400)
            ->assertJsonPath('message', 'You have already used this coupon the maximum number of times');

        // ...and no order/usage row is created for the rejected attempt.
        $this->assertSame(2, Order::where('user_id', $user->id)->count());
        $this->assertSame(2, CouponUsage::where('user_id', $user->id)->count());
    }

    public function test_per_user_limit_is_scoped_per_user_not_global(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100, 'per_user_limit' => 1]);

        $this->placeOrder($a, $product, 'FLAT100')->assertOk();
        $this->placeOrder($a, $product, 'FLAT100')->assertStatus(400);

        // A different user still has their own untouched allowance.
        $this->placeOrder($b, $product, 'FLAT100')->assertOk();
    }

    public function test_a_coupons_global_usage_limit_is_enforced_across_users(): void
    {
        [$a, $b] = User::factory()->count(2)->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'ONEOFF', 'discount' => 100, 'per_user_limit' => null, 'usage_limit' => 1]);

        $this->placeOrder($a, $product, 'ONEOFF')->assertOk();

        $this->placeOrder($b, $product, 'ONEOFF')
            ->assertStatus(400)
            ->assertJsonPath('message', 'This coupon has reached its usage limit');
    }

    public function test_coupon_below_its_minimum_order_value_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 100, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'BIGSPEND', 'discount' => 50, 'min_order_value' => 500]);

        $this->placeOrder($user, $product, 'BIGSPEND')
            ->assertStatus(400)
            ->assertJsonPath('message', 'Minimum order value for this coupon is ₹500.00');
    }

    public function test_first_order_only_coupon_is_rejected_for_a_returning_customer(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'WELCOME', 'discount' => 50, 'first_order_only' => true]);

        // Places an unrelated first order with no coupon...
        $this->placeOrder($user, $product)->assertOk();

        // ...so this account no longer qualifies as first-order.
        $this->placeOrder($user, $product, 'WELCOME')
            ->assertStatus(400)
            ->assertJsonPath('message', 'This coupon is valid only on your first order');
    }

    public function test_percentage_coupon_discount_is_capped_by_max_discount_amount(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 1000, 'discounted_price' => null]);
        Coupon::factory()->create([
            'code' => 'PCT50', 'type' => 'PERCENTAGE', 'discount' => 50, 'max_discount_amount' => 200,
        ]);

        $response = $this->placeOrder($user, $product, 'PCT50')->assertOk();

        // 50% of 1000 would be 500, capped to 200.
        $this->assertSame('200.00', $response->json('data.discountAmount'));
    }

    public function test_unknown_or_inactive_coupon_code_is_rejected(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);

        $this->placeOrder($user, $product, 'DOESNOTEXIST')
            ->assertStatus(400)
            ->assertJsonPath('message', 'This coupon is invalid or no longer active');
    }

    public function test_public_coupons_endpoint_reports_how_many_times_the_caller_has_used_each_coupon(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['price' => 500, 'discounted_price' => null]);
        Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100, 'per_user_limit' => 2]);

        $this->placeOrder($user, $product, 'FLAT100')->assertOk();

        $data = $this->actingAsUser($user)->getJson('/api/v1/coupons')->assertOk()->json('data');
        $this->assertSame(1, collect($data)->firstWhere('code', 'FLAT100')['usedByUser']);
    }

    public function test_public_coupons_endpoint_reports_zero_usage_for_a_guest(): void
    {
        Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100, 'per_user_limit' => 2]);

        // No Sanctum::actingAs() in this test at all — genuinely unauthenticated.
        $data = $this->getJson('/api/v1/coupons')->assertOk()->json('data');
        $this->assertSame(0, collect($data)->firstWhere('code', 'FLAT100')['usedByUser']);
    }
}
