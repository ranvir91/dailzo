<?php

namespace Tests\Feature;

use App\Models\DeliveryAssignment;
use App\Models\DeliveryPartner;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartnerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_succeeds_with_the_right_password_and_fails_otherwise(): void
    {
        $partner = DeliveryPartner::factory()->create(['phone' => '8100000001', 'password' => 'secret123']);

        $this->postJson('/api/v1/partner/login', ['phone_number' => '8100000001', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('success', false);

        $response = $this->postJson('/api/v1/partner/login', ['phone_number' => '8100000001', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonPath('data.partnerId', $partner->id)
            ->assertJsonPath('data.name', $partner->name)
            ->assertJsonStructure(['data' => ['token', 'refreshToken', 'partnerId', 'name']]);

        $this->withToken($response->json('data.token'))
            ->getJson('/api/v1/partner/orders')
            ->assertOk();
    }

    public function test_inactive_partner_cannot_log_in(): void
    {
        DeliveryPartner::factory()->inactive()->create(['phone' => '8100000002', 'password' => 'secret123']);

        $this->postJson('/api/v1/partner/login', ['phone_number' => '8100000002', 'password' => 'secret123'])
            ->assertStatus(401);
    }

    public function test_orders_index_only_shows_this_partners_assignments_and_supports_status_filter(): void
    {
        $partner = DeliveryPartner::factory()->create();
        $other = DeliveryPartner::factory()->create();

        $mine = Order::factory()->create(['status' => 'ASSIGNED']);
        DeliveryAssignment::create(['order_id' => $mine->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        $notMine = Order::factory()->create();
        DeliveryAssignment::create(['order_id' => $notMine->id, 'delivery_partner_id' => $other->id, 'status' => 'ASSIGNED']);

        $delivered = Order::factory()->create();
        DeliveryAssignment::create(['order_id' => $delivered->id, 'delivery_partner_id' => $partner->id, 'status' => 'DELIVERED']);

        $all = $this->actingAsPartner($partner)->getJson('/api/v1/partner/orders')->assertOk();
        $this->assertCount(2, $all->json('data'));

        $pending = $this->actingAsPartner($partner)->getJson('/api/v1/partner/orders?status=PENDING')->assertOk();
        $this->assertCount(1, $pending->json('data'));
        $this->assertSame($mine->id, $pending->json('data.0.id'));

        $completed = $this->actingAsPartner($partner)->getJson('/api/v1/partner/orders?status=COMPLETED')->assertOk();
        $this->assertCount(1, $completed->json('data'));
        $this->assertSame($delivered->id, $completed->json('data.0.id'));
    }

    public function test_orders_index_filters_by_date(): void
    {
        $partner = DeliveryPartner::factory()->create();
        $order = Order::factory()->create();
        $assignment = DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);
        $assignment->forceFill(['created_at' => now('Asia/Kolkata')->subDays(2)])->save();

        $this->actingAsPartner($partner)->getJson('/api/v1/partner/orders')
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAsPartner($partner)
            ->getJson('/api/v1/partner/orders?date='.now('Asia/Kolkata')->subDays(2)->format('Y-m-d'))
            ->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_status_update_follows_forward_only_transitions_and_stamps_a_timestamp(): void
    {
        config(['dailzo.otp_debug' => true]);

        $partner = DeliveryPartner::factory()->create();
        $order = Order::factory()->create(['status' => 'ASSIGNED']);
        DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        $response = $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'OUT_FOR_DELIVERY'])
            ->assertOk();

        $this->assertSame('OUT_FOR_DELIVERY', $response->json('data.deliveryStatus'));
        $this->assertNotNull($response->json('data.statusChangedAt'));
        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);
        $otp = $response->json('data.deliveryOtp');
        $this->assertNotNull($otp, 'OUT_FOR_DELIVERY should hand back the delivery OTP while OTP_DEBUG is on');

        // Can't go back from OUT_FOR_DELIVERY to "OUT_FOR_DELIVERY" again isn't
        // blocked, but DELIVERED -> OUT_FOR_DELIVERY (backwards) must be.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED', 'otp' => $otp])
            ->assertOk()
            ->assertJsonPath('data.deliveryStatus', 'DELIVERED');

        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'OUT_FOR_DELIVERY'])
            ->assertStatus(400);
    }

    public function test_completing_an_order_requires_the_delivery_otp_sent_when_it_went_out_for_delivery(): void
    {
        config(['dailzo.otp_debug' => true]);

        $partner = DeliveryPartner::factory()->create();
        $order = Order::factory()->create(['status' => 'ASSIGNED']);
        DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        // Straight ASSIGNED -> COMPLETED: no OTP was ever generated for this
        // order, so there's nothing valid to check against.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED', 'otp' => '1234'])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $otpResponse = $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'OUT_FOR_DELIVERY'])
            ->assertOk();
        $realOtp = $otpResponse->json('data.deliveryOtp');

        // Missing OTP.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED'])
            ->assertStatus(400);

        // Wrong OTP.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED', 'otp' => 'wrong'])
            ->assertStatus(400);

        $this->assertSame('OUT_FOR_DELIVERY', $order->fresh()->status);

        // Right OTP.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED', 'otp' => $realOtp])
            ->assertOk()
            ->assertJsonPath('data.deliveryStatus', 'DELIVERED');
    }

    public function test_status_update_on_an_order_not_assigned_to_the_caller_is_404(): void
    {
        $order = Order::factory()->create();
        DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => DeliveryPartner::factory()->create()->id, 'status' => 'ASSIGNED']);

        $this->actingAsPartner(DeliveryPartner::factory()->create())
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED'])
            ->assertStatus(404);
    }

    public function test_incident_comment_is_recorded_and_visible_in_the_order_listing(): void
    {
        $partner = DeliveryPartner::factory()->create();
        $order = Order::factory()->create();
        DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        $this->actingAsPartner($partner)
            ->postJson("/api/v1/partner/orders/{$order->id}/comments", ['comment' => 'Customer not at delivery address'])
            ->assertOk()
            ->assertJsonPath('data.body', 'Customer not at delivery address');

        $listing = $this->actingAsPartner($partner)->getJson('/api/v1/partner/orders')->assertOk();
        $this->assertSame('Customer not at delivery address', $listing->json('data.0.deliveryNotes.0.body'));
    }

    public function test_search_requires_at_least_three_characters_and_excludes_self_and_inactive(): void
    {
        $me = DeliveryPartner::factory()->create(['name' => 'Ravi Kumar']);
        $match = DeliveryPartner::factory()->create(['name' => 'Ravi Shastri']);
        DeliveryPartner::factory()->inactive()->create(['name' => 'Ravi Inactive']);

        $this->actingAsPartner($me)->getJson('/api/v1/partner/search?query=ra')
            ->assertOk()->assertJsonCount(0, 'data');

        $response = $this->actingAsPartner($me)->getJson('/api/v1/partner/search?query=Ravi')->assertOk();
        $names = collect($response->json('data'))->pluck('name');

        $this->assertTrue($names->contains('Ravi Shastri'));
        $this->assertFalse($names->contains('Ravi Kumar'));
        $this->assertFalse($names->contains('Ravi Inactive'));
        $this->assertSame($match->id, $response->json('data.0.id'));
    }

    public function test_reassign_moves_the_order_to_the_target_partner(): void
    {
        config(['dailzo.otp_debug' => true]);

        $partner = DeliveryPartner::factory()->create();
        $target = DeliveryPartner::factory()->create();
        $order = Order::factory()->create();
        $original = DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        $this->actingAsPartner($partner)
            ->postJson("/api/v1/partner/orders/{$order->id}/reassign", [
                'target_partner_id' => $target->id,
                'reason' => 'Vehicle breakdown',
            ])
            ->assertOk()
            ->assertJsonPath('data.deliveryStatus', 'ASSIGNED');

        $this->assertSame('REASSIGNED', $original->fresh()->status);
        $this->assertDatabaseHas('delivery_assignments', [
            'order_id' => $order->id,
            'delivery_partner_id' => $target->id,
            'status' => 'ASSIGNED',
        ]);

        // Original partner no longer owns it.
        $this->actingAsPartner($partner)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'COMPLETED'])
            ->assertStatus(404);

        // New partner does — but still needs the delivery OTP to close it out.
        $otpResponse = $this->actingAsPartner($target)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", ['status' => 'OUT_FOR_DELIVERY'])
            ->assertOk();

        $this->actingAsPartner($target)
            ->patchJson("/api/v1/partner/orders/{$order->id}/status", [
                'status' => 'COMPLETED',
                'otp' => $otpResponse->json('data.deliveryOtp'),
            ])
            ->assertOk();
    }

    public function test_cannot_reassign_to_self(): void
    {
        $partner = DeliveryPartner::factory()->create();
        $order = Order::factory()->create();
        DeliveryAssignment::create(['order_id' => $order->id, 'delivery_partner_id' => $partner->id, 'status' => 'ASSIGNED']);

        $this->actingAsPartner($partner)
            ->postJson("/api/v1/partner/orders/{$order->id}/reassign", [
                'target_partner_id' => $partner->id,
                'reason' => 'x',
            ])
            ->assertStatus(400);
    }

    public function test_customer_token_cannot_access_partner_routes(): void
    {
        $this->actingAsUser(\App\Models\User::factory()->create())
            ->getJson('/api/v1/partner/orders')
            ->assertStatus(403);
    }
}
