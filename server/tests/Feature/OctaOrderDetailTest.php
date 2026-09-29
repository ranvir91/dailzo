<?php

namespace Tests\Feature;

use App\Filament\Octa\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Octa\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class OctaOrderDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('octa'));
    }

    public function test_the_order_detail_page_shows_coupon_discount_and_item_images(): void
    {
        Storage::fake('web');
        Storage::disk('web')->put('uploads/rice.png', 'fake-image-bytes');

        $vendor = Vendor::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100]);
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

        $this->actingAs($vendor->user);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('FLAT100')
            ->assertSee('Basmati Rice 5kg')
            ->assertSee('rice.png', escape: false);
    }

    public function test_the_order_detail_page_renders_without_a_coupon_or_address(): void
    {
        $vendor = Vendor::factory()->create();
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'coupon_id' => null,
            'discount_amount' => 0,
            'address_id' => null,
        ]);

        $this->actingAs($vendor->user);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])
            ->assertOk()
            ->assertSee('No coupon applied')
            ->assertSee('No address on file');
    }

    public function test_the_order_list_shows_coupon_discount_and_item_image_columns(): void
    {
        Storage::fake('web');
        Storage::disk('web')->put('uploads/rice.png', 'fake-image-bytes');

        $vendor = Vendor::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'FLAT100', 'discount' => 100]);
        $product = Product::factory()->create(['images' => ['uploads/rice.png']]);
        $order = Order::factory()->create([
            'vendor_id' => $vendor->id,
            'coupon_id' => $coupon->id,
            'discount_amount' => 100,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 250,
        ]);

        $this->actingAs($vendor->user);

        Livewire::test(ListOrders::class)
            ->assertOk()
            ->assertSee('FLAT100')
            ->assertSee('rice.png', escape: false);
    }
}
