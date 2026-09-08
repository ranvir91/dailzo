<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_returns_the_code_in_debug_mode(): void
    {
        config()->set('dailzo.otp_debug', true);

        $response = $this->postJson('/api/v1/auth/send-otp', ['phone' => '9800000001'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['phone', 'expiresIn', 'otp']]);

        $this->assertSame(Cache::get('otp:9800000001'), $response->json('data.otp'));
    }

    public function test_send_otp_hides_the_code_when_debug_is_off(): void
    {
        config()->set('dailzo.otp_debug', false);

        $this->postJson('/api/v1/auth/send-otp', ['phone' => '9800000002'])
            ->assertOk()
            ->assertJsonMissingPath('data.otp');
    }

    public function test_login_creates_a_user_and_issues_tokens(): void
    {
        Cache::put('otp:9800000003', '1234', 300);

        $response = $this->postJson('/api/v1/auth/login', ['phone' => '9800000003', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('message', 'Login successful')
            ->assertJsonStructure(['data' => ['user' => ['id', 'phone'], 'accessToken', 'refreshToken']]);

        $this->assertDatabaseHas('users', ['phone' => '9800000003', 'role' => 'CUSTOMER']);

        $token = $response->json('data.accessToken');
        $this->withToken($token)->getJson('/api/v1/users/me')
            ->assertOk()
            ->assertJsonPath('data.phone', '9800000003');
    }

    public function test_login_rejects_a_wrong_otp(): void
    {
        Cache::put('otp:9800000004', '1234', 300);

        $this->postJson('/api/v1/auth/login', ['phone' => '9800000004', 'otp' => '9999'])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Invalid OTP');
    }

    public function test_refresh_token_issues_a_new_access_token(): void
    {
        $user = User::factory()->create();
        Cache::put("otp:{$user->phone}", '1234', 300);

        $refresh = $this->postJson('/api/v1/auth/login', ['phone' => $user->phone, 'otp' => '1234'])
            ->json('data.refreshToken');

        $this->postJson('/api/v1/auth/refresh-token', ['refreshToken' => $refresh])
            ->assertOk()
            ->assertJsonStructure(['data' => ['user', 'accessToken']]);
    }
}
