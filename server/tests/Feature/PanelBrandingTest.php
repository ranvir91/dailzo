<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Tests\TestCase;

class PanelBrandingTest extends TestCase
{
    public function test_atlas_shows_the_brand_name_next_to_the_dailzo_logo(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Atlas')
            ->assertSee('images/Dailzo_logo.png', escape: false);
    }

    public function test_octa_shows_the_brand_name_next_to_the_dailzo_logo(): void
    {
        $vendor = Vendor::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('octa'));
        $this->actingAs($vendor->user);

        $this->get('/octa')
            ->assertOk()
            ->assertSee('Octa')
            ->assertSee('images/Dailzo_logo.png', escape: false);
    }

    public function test_the_collapsed_sidebar_expand_button_uses_the_dailzo_icon_on_atlas(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertSee('$store.sidebar.open()', escape: false)
            ->assertSee('images/Dailzo_icon.png', escape: false);
    }

    public function test_the_collapsed_sidebar_expand_button_uses_the_dailzo_icon_on_octa(): void
    {
        $vendor = Vendor::factory()->create();
        Filament::setCurrentPanel(Filament::getPanel('octa'));
        $this->actingAs($vendor->user);

        $this->get('/octa')
            ->assertOk()
            ->assertSee('$store.sidebar.open()', escape: false)
            ->assertSee('images/Dailzo_icon.png', escape: false);
    }
}
