<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ServicePincodeResource;
use App\Filament\Resources\UserResource;
use App\Filament\Widgets\StoreOverview;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_the_dashboard_shows_a_pincodes_tile_and_every_tile_links_to_its_page(): void
    {
        // Filament widgets are lazy-loaded by default (Widget::$isLazy), so
        // a plain HTTP GET of /admin only returns a placeholder — testing
        // the widget component directly is what actually exercises its
        // render cycle (getStats()), same as a real page does once its
        // wire:init fires.
        Livewire::test(StoreOverview::class)
            ->assertSee('Pincodes')
            ->assertSee('Orders')
            ->assertSee('Revenue (delivered)')
            ->assertSee('Customers')
            ->assertSee('Products')
            ->assertSeeHtml(OrderResource::getUrl())
            ->assertSeeHtml(ProductResource::getUrl())
            ->assertSeeHtml(UserResource::getUrl())
            ->assertSeeHtml(ServicePincodeResource::getUrl());
    }
}
