<?php

namespace Tests\Feature;

use App\Filament\Resources\BrandResource\Pages\ManageBrands;
use App\Models\Brand;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasBrandResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_can_list_brands(): void
    {
        Brand::factory()->create(['name' => 'Acme']);

        Livewire::test(ManageBrands::class)->assertSee('Acme');
    }

    public function test_a_brand_can_be_created_with_an_auto_filled_slug(): void
    {
        Livewire::test(ManageBrands::class)
            ->mountAction('create')
            ->setActionData(['name' => 'Acme Corp', 'slug' => 'acme-corp'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $brand = Brand::where('name', 'Acme Corp')->firstOrFail();
        $this->assertSame('acme-corp', $brand->slug);
        $this->assertTrue($brand->is_active);
    }
}
