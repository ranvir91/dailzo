<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\DeliveryPartner;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasUserListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_landing_on_the_list_shows_only_customers_by_default(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $admin = User::factory()->admin()->create();
        $vendorUser = Vendor::factory()->create()->user;

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$customer])
            ->assertCanNotSeeTableRecords([$admin, $vendorUser]);
    }

    public function test_switching_the_show_filter_to_vendors_lists_only_vendors(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $vendorUser = Vendor::factory()->create()->user;

        Livewire::test(ListUsers::class)
            ->set('userType', 'VENDOR')
            ->assertCanSeeTableRecords([$vendorUser])
            ->assertCanNotSeeTableRecords([$customer]);
    }

    public function test_switching_the_show_filter_to_delivery_partners_lists_only_delivery_partners(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $partnerUser = DeliveryPartner::factory()->create()->user;

        Livewire::test(ListUsers::class)
            ->set('userType', 'DELIVERY_PARTNER')
            ->assertCanSeeTableRecords([$partnerUser])
            ->assertCanNotSeeTableRecords([$customer]);
    }

    public function test_clearing_the_filter_shows_all_user_types(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);
        $admin = User::factory()->admin()->create();
        $vendorUser = Vendor::factory()->create()->user;

        Livewire::test(ListUsers::class)
            ->set('userType', null)
            ->assertCanSeeTableRecords([$customer, $admin, $vendorUser]);
    }

    public function test_vendor_rows_reached_via_all_user_types_are_not_editable_from_here(): void
    {
        $vendorUser = Vendor::factory()->create()->user;

        Livewire::test(ListUsers::class)
            ->set('userType', null)
            ->assertTableActionHidden('edit', $vendorUser)
            ->assertTableActionHidden('delete', $vendorUser);
    }

    public function test_customer_rows_stay_editable(): void
    {
        $customer = User::factory()->create(['role' => 'CUSTOMER']);

        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('edit', $customer)
            ->assertTableActionVisible('delete', $customer);
    }

    public function test_the_inline_show_select_renders_with_all_role_options(): void
    {
        // A real HTTP hit, not Livewire::test() in isolation: the render hook
        // is registered during the panel's own boot() (see AdminPanelProvider),
        // which only runs for a real request through the panel's routes/
        // middleware — Livewire::test() alone doesn't trigger it.
        $this->get(UserResource::getUrl())
            ->assertOk()
            ->assertSee('All user types')
            ->assertSee('Vendors')
            ->assertSee('Delivery partners');
    }
}
