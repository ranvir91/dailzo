<?php

namespace Tests\Feature;

use App\Filament\Resources\OfferResource\Pages\CreateOffer;
use App\Filament\Resources\OfferResource\Pages\ListOffers;
use App\Models\Offer;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasOfferResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_offers(): void
    {
        Offer::factory()->create(['title' => 'Diwali Sale']);

        Livewire::test(ListOffers::class)->assertSee('Diwali Sale');
    }

    public function test_picking_a_duration_preset_fills_in_the_expiry_date(): void
    {
        $today = now('Asia/Kolkata')->startOfDay();

        Livewire::test(CreateOffer::class)
            ->fillForm([
                'title' => '10 Days Sale',
                'starts_at' => $today->toDateString(),
                'duration_preset' => '10',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::where('title', '10 Days Sale')->firstOrFail();
        $this->assertSame(
            $today->copy()->addDays(10)->endOfDay()->toDateString(),
            $offer->expires_at->timezone('Asia/Kolkata')->toDateString(),
        );
    }

    public function test_creating_an_offer_without_a_duration_leaves_it_open_ended(): void
    {
        Livewire::test(CreateOffer::class)
            ->fillForm(['title' => 'Always On'])
            ->call('create')
            ->assertHasNoFormErrors();

        $offer = Offer::where('title', 'Always On')->firstOrFail();
        $this->assertNull($offer->expires_at);
    }
}
