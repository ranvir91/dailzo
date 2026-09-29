<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_order_detail_page_shows_coupon_discount_and_item_images(): void
    {
        Storage::fake('web');
        Storage::disk('web')->put('uploads/rice.png', 'fake-image-bytes');

        $coupon = Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100]);
        $vendor = Vendor::factory()->create();
        $product = Product::factory()->create(['name' => 'Basmati Rice 5kg', 'images' => ['uploads/rice.png']]);
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'coupon_id' => $coupon->id,
            'discount_amount' => 100,
            'total' => 400,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 250,
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('FLAT100')
            ->assertSee('Basmati Rice 5kg')
            ->assertSee('rice.png', escape: false);
    }

    public function test_the_order_detail_page_renders_without_a_coupon_address_or_vendor(): void
    {
        // Every relation on the infolist (coupon, address, vendor, delivery
        // partner) is optional — this exercises the placeholder/null path
        // for all of them at once, not just the happy path above.
        $order = Order::factory()->create([
            'vendor_id' => null,
            'coupon_id' => null,
            'discount_amount' => 0,
            'address_id' => null,
        ]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('No coupon applied')
            ->assertSee('No address on file')
            ->assertSee('Unassigned');
    }

    public function test_the_order_detail_page_shows_delivery_charges_when_applicable(): void
    {
        $order = Order::factory()->create(['delivery_charges' => 49]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('Delivery charges')
            ->assertSee('49.00');
    }

    public function test_the_order_detail_page_hides_delivery_charges_when_free(): void
    {
        $order = Order::factory()->create(['delivery_charges' => 0]);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertDontSee('Delivery charges');
    }
}
