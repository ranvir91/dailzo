<?php

namespace Tests;

use App\Models\DeliveryPartner;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Rate limits are keyed by IP/phone; every test hits from 127.0.0.1, so
        // leave throttling to its own dedicated test rather than tripping it here.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /** Authenticate the following request(s) as $user via a Sanctum token. */
    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user, ['*']);

        return $this;
    }

    /**
     * Authenticate the following request(s) as $partner via a Sanctum token.
     * Partner login goes through the linked User now (role = DELIVERY_PARTNER),
     * so the token is actually issued to that User — this helper still takes a
     * DeliveryPartner for convenience at the call site.
     */
    protected function actingAsPartner(DeliveryPartner $partner): static
    {
        Sanctum::actingAs($partner->user, ['*']);

        return $this;
    }
}
