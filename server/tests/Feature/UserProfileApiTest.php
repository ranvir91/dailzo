<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for a bug introduced by the UUID -> auto-increment
 * int id migration: UserController::canActOn() compared
 * $request->user()->id (now a native int) against the {id} route param
 * (always a string) with ===, which is never true for two differently
 * typed values that represent the same id — every self-update/self-show
 * silently failed with "User not found" even though the user existed.
 */
class UserProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_view_their_own_profile_by_id(): void
    {
        $user = User::factory()->create();

        $this->actingAsUser($user)
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_a_user_can_update_their_own_profile(): void
    {
        $user = User::factory()->create(['name' => 'Old Name']);

        $this->actingAsUser($user)
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('message', 'User updated successfully')
            ->assertJsonPath('data.name', 'New Name');

        $this->assertSame('New Name', $user->fresh()->name);
    }

    public function test_a_user_cannot_update_another_users_profile(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Untouched']);

        $this->actingAsUser($user)
            ->patchJson("/api/v1/users/{$other->id}", ['name' => 'Hacked'])
            ->assertOk()
            ->assertJsonPath('message', 'User not found');

        $this->assertSame('Untouched', $other->fresh()->name);
    }

    public function test_an_admin_can_view_and_update_another_users_profile(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Original']);

        $this->actingAsUser($admin)
            ->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);

        $this->actingAsUser($admin)
            ->patchJson("/api/v1/users/{$user->id}", ['name' => 'Updated by admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated by admin');
    }

    public function test_a_user_cannot_delete_their_own_account(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAsUser($user)
            ->deleteJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('message', 'You cannot delete your own account');

        $this->assertNotNull($user->fresh());
    }
}
