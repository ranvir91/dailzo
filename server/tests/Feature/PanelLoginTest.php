<?php

namespace Tests\Feature;

use App\Filament\Auth\Login;
use App\Models\User;
use App\Models\Vendor;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PanelLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_log_in_to_atlas_with_phone_and_password(): void
    {
        $admin = User::factory()->admin()->create(['phone' => '9000000001', 'password' => 'secretpass']);

        Livewire::test(Login::class)
            ->fillForm(['login' => '9000000001', 'password' => 'secretpass'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_admin_can_log_in_to_atlas_with_email_and_password(): void
    {
        $admin = User::factory()->admin()->create(['email' => 'boss@dailzo.test', 'password' => 'secretpass']);

        Livewire::test(Login::class)
            ->fillForm(['login' => 'boss@dailzo.test', 'password' => 'secretpass'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_wrong_password_is_rejected(): void
    {
        User::factory()->admin()->create(['phone' => '9000000002', 'password' => 'secretpass']);

        Livewire::test(Login::class)
            ->fillForm(['login' => '9000000002', 'password' => 'wrong'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_unknown_login_identifier_is_rejected_without_error(): void
    {
        Livewire::test(Login::class)
            ->fillForm(['login' => 'nobody@dailzo.test', 'password' => 'whatever'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_a_customer_cannot_log_in_to_atlas_even_with_the_right_password(): void
    {
        User::factory()->create(['phone' => '9000000003', 'password' => 'secretpass', 'role' => 'CUSTOMER']);

        Livewire::test(Login::class)
            ->fillForm(['login' => '9000000003', 'password' => 'secretpass'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest();
    }

    public function test_vendor_can_log_in_to_octa_with_phone_and_password_but_not_atlas(): void
    {
        $vendor = Vendor::factory()->create();
        $vendor->user->update(['password' => 'secretpass']);

        // Rejected on Atlas...
        Livewire::test(Login::class)
            ->fillForm(['login' => $vendor->user->phone, 'password' => 'secretpass'])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);
        $this->assertGuest();

        // ...but works on Octa, the panel they actually belong to.
        Filament::setCurrentPanel(Filament::getPanel('octa'));

        Livewire::test(Login::class)
            ->fillForm(['login' => $vendor->user->phone, 'password' => 'secretpass'])
            ->call('authenticate');

        $this->assertAuthenticatedAs($vendor->user);
    }
}
