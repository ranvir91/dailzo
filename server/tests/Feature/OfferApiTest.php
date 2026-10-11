<?php

namespace Tests\Feature;

use App\Models\Offer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_in_window_offers_are_public(): void
    {
        Offer::factory()->create(['title' => 'Live offer']);
        Offer::factory()->expired()->create(['title' => 'Old offer']);
        Offer::factory()->inactive()->create(['title' => 'Off offer']);
        Offer::factory()->create(['title' => 'Future offer', 'starts_at' => now()->addDay()]);

        $titles = collect($this->getJson('/api/v1/offers')->assertOk()->json('data'))->pluck('title');

        $this->assertTrue($titles->contains('Live offer'));
        $this->assertFalse($titles->contains('Old offer'));
        $this->assertFalse($titles->contains('Off offer'));
        $this->assertFalse($titles->contains('Future offer'));
    }

    public function test_offers_are_ordered_by_sort_order_then_newest_first(): void
    {
        Offer::factory()->create(['title' => 'B', 'sort_order' => 1]);
        Offer::factory()->create(['title' => 'A', 'sort_order' => 0]);

        $titles = collect($this->getJson('/api/v1/offers')->assertOk()->json('data'))->pluck('title');

        $this->assertSame(['A', 'B'], $titles->all());
    }

    public function test_offer_ids_are_plain_integers(): void
    {
        Offer::factory()->create();

        $data = $this->getJson('/api/v1/offers')->assertOk()->json('data');
        $this->assertIsInt($data[0]['id']);
    }
}
