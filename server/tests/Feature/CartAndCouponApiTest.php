<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartAndCouponApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_items_persist_for_the_user(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $this->actingAsUser($user)->postJson('/api/v1/cart/items', [
            'productId' => $product->id,
            'quantity' => 3,
        ])->assertOk()->assertJsonPath('data.items.0.quantity', 3);

        $this->actingAsUser($user)->getJson('/api/v1/cart')
            ->assertOk()
            ->assertJsonPath('data.items.0.productId', $product->id);

        $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 3]);
    }

    public function test_only_active_in_window_coupons_are_public(): void
    {
        Coupon::factory()->create(['code' => 'LIVE']);
        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->inactive()->create(['code' => 'OFF']);

        $codes = collect($this->getJson('/api/v1/coupons')->json('data'))->pluck('code');

        $this->assertTrue($codes->contains('LIVE'));
        $this->assertFalse($codes->contains('OLD'));
        $this->assertFalse($codes->contains('OFF'));
    }

    public function test_coupon_lookup_is_case_insensitive(): void
    {
        Coupon::factory()->create(['code' => 'SAVE20']);

        $this->getJson('/api/v1/coupons/save20')
            ->assertOk()
            ->assertJsonPath('data.code', 'SAVE20');
    }

    public function test_admin_coupon_create_validates_type(): void
    {
        $this->actingAsUser(User::factory()->admin()->create())
            ->postJson('/api/v1/coupons', ['code' => 'X', 'type' => 'BOGUS', 'discount' => 10])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Coupon type must be FIXED or PERCENTAGE');
    }
}
