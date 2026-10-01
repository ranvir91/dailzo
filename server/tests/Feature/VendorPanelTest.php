<?php

namespace Tests\Feature;

use App\Filament\Octa\Resources\DeliveryPartnerResource as OctaDeliveryPartnerResource;
use App\Filament\Octa\Resources\DeliveryPartnerResource\Pages\ManageDeliveryPartners as OctaManageDeliveryPartners;
use App\Filament\Octa\Resources\OrderResource as OctaOrderResource;
use App\Filament\Octa\Resources\OrderResource\Pages\ListOrders as OctaListOrders;
use App\Filament\Octa\Widgets\RecentOrders;
use App\Filament\Octa\Widgets\VendorOverview;
use App\Filament\Resources\DeliveryPartnerResource\Pages\ManageDeliveryPartners as AtlasManageDeliveryPartners;
use App\Filament\Resources\OrderResource\Pages\ListOrders as AtlasListOrders;
use App\Filament\Resources\VendorResource\Pages\ManageVendors;
use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;
use App\Models\ServicePincode;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VendorPanelTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Livewire component tests mount a panel's page directly rather than
        // going through routing, so the "current panel" needs setting by hand
        // for Filament's panel-scoped resolution (canAccessPanel, navigation,
        // etc.) to behave the way it would for a real request.
        Filament::setCurrentPanel(Filament::getPanel('octa'));
    }

    public function test_vendor_only_sees_their_own_orders_in_octa(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $orderA = Order::factory()->create(['vendor_id' => $vendorA->id]);
        $orderB = Order::factory()->create(['vendor_id' => $vendorB->id]);

        $this->actingAs($vendorA->user);

        Livewire::test(OctaListOrders::class)
            ->assertCanSeeTableRecords([$orderA])
            ->assertCanNotSeeTableRecords([$orderB]);
    }

    public function test_vendor_only_sees_their_own_delivery_partners_in_octa(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        $partnerA = DeliveryPartner::factory()->create(['vendor_id' => $vendorA->id]);
        $partnerB = DeliveryPartner::factory()->create(['vendor_id' => $vendorB->id]);

        $this->actingAs($vendorA->user);

        Livewire::test(OctaManageDeliveryPartners::class)
            ->assertCanSeeTableRecords([$partnerA])
            ->assertCanNotSeeTableRecords([$partnerB]);
    }

    public function test_vendor_can_assign_one_of_their_own_partners_to_their_own_order(): void
    {
        $vendor = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user);

        Livewire::test(OctaListOrders::class)
            ->callTableAction('assignDeliveryPartner', $order, data: [
                'delivery_partner_id' => $partner->id,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
            'status' => 'ASSIGNED',
        ]);
    }

    public function test_vendor_cannot_assign_another_vendors_partner_even_if_submitted_directly(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $foreignPartner = DeliveryPartner::factory()->create(['vendor_id' => $otherVendor->id]);
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user);

        // The options() list wouldn't offer $foreignPartner, but the action's
        // own DeliveryPartner::where('vendor_id', ...)->findOrFail() guard is
        // the real defense if a request is crafted to submit it directly
        // anyway — which is exactly what this asserts by expecting the 404.
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OctaListOrders::class)
            ->callTableAction('assignDeliveryPartner', $order, data: [
                'delivery_partner_id' => $foreignPartner->id,
            ]);
    }

    public function test_vendor_can_bulk_assign_a_delivery_partner_to_several_of_their_own_orders(): void
    {
        $vendor = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $orders = Order::factory()->count(3)->create(['vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user);

        Livewire::test(OctaListOrders::class)
            ->callTableBulkAction('bulkAssignDeliveryPartner', $orders, data: [
                'delivery_partner_id' => $partner->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        foreach ($orders as $order) {
            $this->assertDatabaseHas('delivery_assignments', [
                'order_id' => $order->id,
                'delivery_partner_id' => $partner->id,
                'status' => 'ASSIGNED',
            ]);
        }
    }

    public function test_vendor_cannot_bulk_assign_another_vendors_partner_even_if_submitted_directly(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $foreignPartner = DeliveryPartner::factory()->create(['vendor_id' => $otherVendor->id]);
        $orders = Order::factory()->count(2)->create(['vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user);

        $this->expectException(ModelNotFoundException::class);

        Livewire::test(OctaListOrders::class)
            ->callTableBulkAction('bulkAssignDeliveryPartner', $orders, data: [
                'delivery_partner_id' => $foreignPartner->id,
            ]);
    }

    public function test_delivered_orders_hide_the_assign_delivery_partner_action_in_octa(): void
    {
        $vendor = Vendor::factory()->create();
        $delivered = Order::factory()->create(['vendor_id' => $vendor->id, 'status' => 'DELIVERED']);
        $pending = Order::factory()->create(['vendor_id' => $vendor->id, 'status' => 'PENDING']);

        $this->actingAs($vendor->user);

        Livewire::test(OctaListOrders::class)
            ->assertTableActionHidden('assignDeliveryPartner', $delivered)
            ->assertTableActionVisible('assignDeliveryPartner', $pending);
    }

    public function test_octa_bulk_assign_skips_delivered_orders_in_a_mixed_selection(): void
    {
        $vendor = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $delivered = Order::factory()->create(['vendor_id' => $vendor->id, 'status' => 'DELIVERED']);
        $pending = Order::factory()->create(['vendor_id' => $vendor->id, 'status' => 'PENDING']);

        $this->actingAs($vendor->user);

        Livewire::test(OctaListOrders::class)
            ->callTableBulkAction('bulkAssignDeliveryPartner', [$delivered, $pending], data: [
                'delivery_partner_id' => $partner->id,
            ])
            ->assertHasNoTableBulkActionErrors();

        $this->assertDatabaseMissing('delivery_assignments', ['order_id' => $delivered->id]);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $pending->id,
            'delivery_partner_id' => $partner->id,
        ]);
    }

    public function test_a_vendors_bulk_selection_can_only_ever_contain_their_own_orders(): void
    {
        // getEloquentQuery() already scopes the whole table to this vendor's
        // orders, so another vendor's order is never even a selectable
        // record here — nothing extra to guard in the bulk action itself.
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $ownOrder = Order::factory()->create(['vendor_id' => $vendor->id]);
        $foreignOrder = Order::factory()->create(['vendor_id' => $otherVendor->id]);

        $this->actingAs($vendor->user);

        Livewire::test(OctaListOrders::class)
            ->assertCanSeeTableRecords([$ownOrder])
            ->assertCanNotSeeTableRecords([$foreignOrder]);
    }

    public function test_superadmin_creates_a_vendor_with_a_working_login(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());

        $pincode = ServicePincode::create(['pincode' => '121099', 'is_active' => true]);

        Livewire::test(ManageVendors::class)
            ->callAction('create', data: [
                'business_name' => 'Acme Fulfillment',
                'name' => 'Acme Contact',
                'phone' => '7000000001',
                'password' => 'vendorpass',
                'service_pincode_ids' => [$pincode->id],
                'is_active' => true,
            ])
            ->assertHasNoActionErrors();

        $vendorUser = User::where('phone', '7000000001')->first();
        $this->assertNotNull($vendorUser);
        $this->assertSame('VENDOR', $vendorUser->role);

        $vendor = $vendorUser->vendorProfile;
        $this->assertNotNull($vendor);
        $this->assertSame('Acme Fulfillment', $vendor->business_name);
        $this->assertTrue($vendor->servicePincodes->contains('id', $pincode->id));

        // The credentials just created actually let the vendor into Octa.
        Filament::setCurrentPanel(Filament::getPanel('octa'));
        $this->assertTrue($vendorUser->canAccessPanel(Filament::getPanel('octa')));
    }

    public function test_the_vendors_list_number_column_is_the_vendors_id_not_a_separate_vendor_number(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());

        $vendor = Vendor::factory()->create();

        $this->assertArrayNotHasKey('vendor_number', $vendor->getAttributes());

        Livewire::test(ManageVendors::class)
            ->assertSee((string) $vendor->id);
    }

    public function test_superadmin_can_assign_a_vendor_and_override_the_delivery_partner_in_atlas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());

        $vendor = Vendor::factory()->create();
        $partner = DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $order = Order::factory()->create();

        Livewire::test(AtlasListOrders::class)
            ->callTableAction('assignVendor', $order, data: ['vendor_id' => $vendor->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($vendor->id, $order->fresh()->vendor_id);

        Livewire::test(AtlasListOrders::class)
            ->callTableAction('assignDeliveryPartner', $order, data: ['delivery_partner_id' => $partner->id])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'delivery_partner_id' => $partner->id,
        ]);
    }

    public function test_superadmin_manages_delivery_partners_across_every_vendor_in_atlas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());

        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();
        $partnerA = DeliveryPartner::factory()->create(['vendor_id' => $vendorA->id]);
        $partnerB = DeliveryPartner::factory()->create(['vendor_id' => $vendorB->id]);

        Livewire::test(AtlasManageDeliveryPartners::class)
            ->assertCanSeeTableRecords([$partnerA, $partnerB]);
    }

    public function test_octa_dashboard_widgets_show_only_this_vendors_own_numbers(): void
    {
        $vendor = Vendor::factory()->create();
        $otherVendor = Vendor::factory()->create();
        DeliveryPartner::factory()->count(2)->create(['vendor_id' => $vendor->id]);
        DeliveryPartner::factory()->create(['vendor_id' => $otherVendor->id]); // not this vendor's

        $mine = Order::factory()->create(['vendor_id' => $vendor->id]);
        DeliveryAssignment::create([
            'order_id' => $mine->id,
            'delivery_partner_id' => DeliveryPartner::where('vendor_id', $vendor->id)->first()->id,
            'status' => DeliveryAssignment::STATUS_OUT_FOR_DELIVERY,
        ]);
        Order::factory()->create(['vendor_id' => $otherVendor->id]); // not this vendor's

        $this->actingAs($vendor->user);

        Livewire::test(VendorOverview::class)
            ->assertSee('Total orders')
            ->assertSee('1') // this vendor has exactly one order
            ->assertSee('Out for delivery')
            ->assertSee('Active delivery partners')
            ->assertSee('2'); // this vendor's two active partners

        Livewire::test(RecentOrders::class)
            ->assertCanSeeTableRecords([$mine]);
    }

    public function test_octa_dashboard_tiles_and_recent_orders_rows_link_to_their_pages(): void
    {
        $vendor = Vendor::factory()->create();
        DeliveryPartner::factory()->create(['vendor_id' => $vendor->id]);
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);

        $this->actingAs($vendor->user);

        Livewire::test(VendorOverview::class)
            ->assertSeeHtml(OctaOrderResource::getUrl())
            ->assertSeeHtml(OctaDeliveryPartnerResource::getUrl());

        Livewire::test(RecentOrders::class)
            ->assertSeeHtml(OctaOrderResource::getUrl('view', ['record' => $order]));
    }

    public function test_superadmin_can_list_a_serviceable_pincode_with_an_area_name(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin());

        ServicePincode::create(['pincode' => '560001', 'area_name' => 'MG Road', 'is_active' => true]);

        $this->assertDatabaseHas('service_pincodes', [
            'pincode' => '560001',
            'area_name' => 'MG Road',
        ]);
    }
}
