<?php

namespace Tests\Feature;

use App\Filament\Resources\CouponResource\Pages\ListCoupons;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasCouponListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_coupons_table_exposes_every_field_as_a_column(): void
    {
        $columns = Livewire::test(ListCoupons::class)->instance()->getTable()->getColumns();
        $names = collect($columns)->map(fn ($column) => $column->getName())->all();

        foreach ([
            'code', 'description', 'type', 'discount', 'max_discount_amount',
            'min_order_value', 'starts_at', 'expires_at', 'usages_count',
            'per_user_limit', 'first_order_only', 'is_active', 'created_at',
        ] as $expected) {
            $this->assertContains($expected, $names, "Missing coupon column: {$expected}");
        }
    }

    public function test_visible_by_default_columns_render_usage_validity_and_first_order_only(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'FLAT100',
            'per_user_limit' => 2,
            'usage_limit' => 10,
            'expires_at' => now()->addDays(5)->startOfDay(),
            'first_order_only' => true,
        ]);
        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => User::factory()->create()->id]);

        Livewire::test(ListCoupons::class)
            ->assertSee('FLAT100')
            ->assertSee('1 / 10') // usage count / usage_limit
            ->assertSee($coupon->expires_at->format('M'))
            ->assertSee('2'); // per-user limit
    }

    public function test_unlimited_usage_and_per_user_limits_show_a_placeholder_not_blank(): void
    {
        Coupon::factory()->create([
            'code' => 'NOLIMIT',
            'usage_limit' => null,
            'per_user_limit' => null,
        ]);

        Livewire::test(ListCoupons::class)
            ->assertSee('0 / ∞')
            ->assertSee('Unlimited');
    }
}
