<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_returns_the_envelope(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'ok', 'service' => 'dailzo-backend'],
            ]);
    }

    public function test_unknown_route_returns_envelope_404(): void
    {
        $this->getJson('/api/v1/nope')
            ->assertNotFound()
            ->assertJson(['success' => false]);
    }

    public function test_protected_route_without_token_returns_envelope_401(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertUnauthorized()
            ->assertJson(['success' => false, 'message' => 'Please login to continue']);
    }
}
