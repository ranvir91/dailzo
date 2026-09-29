<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasOrderActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_delivered_orders_hide_the_assign_vendor_and_assign_delivery_partner_row_actions(): void
    {
        $delivered = Order::factory()->create(['status' => 'DELIVERED']);
        $pending = Order::factory()->create(['status' => 'PENDING']);

        $component = Livewire::test(ListOrders::class);

        $component->assertTableActionHidden('assignVendor', $delivered)
            ->assertTableActionHidden('assignDeliveryPartner', $delivered)
            ->assertTableActionVisible('assignVendor', $pending)
            ->assertTableActionVisible('assignDeliveryPartner', $pending);
    }

    public function test_view_action_stays_visible_on_a_delivered_order(): void
    {
        $delivered = Order::factory()->create(['status' => 'DELIVERED']);

        Livewire::test(ListOrders::class)
            ->assertTableActionVisible('view', $delivered);
    }

    public function test_bulk_assign_vendor_applies_to_every_selected_order(): void
    {
        $vendor = Vendor::factory()->create();
        $orders = Order::factory()->count(3)->create(['status' => 'PENDING']);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulkAssignVendor', $orders, data: ['vendor_id' => $vendor->id])
            ->assertHasNoTableBulkActionErrors();

        foreach ($orders as $order) {
            $this->assertSame($vendor->id, $order->fresh()->vendor_id);
        }
    }

    public function test_bulk_assign_delivery_partner_is_not_limited_to_a_single_vendors_partners(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendorB->id]);

        $orderOnVendorA = Order::factory()->create(['status' => 'PENDING', 'vendor_id' => $vendorA->id]);
        $orderUnassigned = Order::factory()->create(['status' => 'PENDING']);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction(
                'bulkAssignDeliveryPartner',
                [$orderOnVendorA, $orderUnassigned],
                data: ['delivery_partner_id' => $partner->id],
            )
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $orderOnVendorA->id,
            'delivery_partner_id' => $partner->id,
        ]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $orderUnassigned->id,
            'delivery_partner_id' => $partner->id,
        ]);
    }

    public function test_bulk_assign_skips_delivered_orders_in_a_mixed_selection(): void
    {
        $vendor = Vendor::factory()->create();
        $delivered = Order::factory()->create(['status' => 'DELIVERED']);
        $pending = Order::factory()->create(['status' => 'PENDING']);

        Livewire::test(ListOrders::class)
            ->callTableBulkAction('bulkAssignVendor', [$delivered, $pending], data: ['vendor_id' => $vendor->id])
            ->assertHasNoTableBulkActionErrors();

        $this->assertNull($delivered->fresh()->vendor_id);
        $this->assertSame($vendor->id, $pending->fresh()->vendor_id);
    }
}
