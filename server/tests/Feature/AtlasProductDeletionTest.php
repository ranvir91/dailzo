<?php

namespace Tests\Feature;

use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasProductDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_deleting_a_product_removes_its_image_files(): void
    {
        Storage::fake('web');
        Storage::disk('web')->put('uploads/one.png', 'x');
        Storage::disk('web')->put('uploads/two.png', 'x');

        $product = Product::factory()->create(['images' => ['uploads/one.png', 'uploads/two.png']]);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product)
            ->assertHasNoTableActionErrors();

        Storage::disk('web')->assertMissing('uploads/one.png');
        Storage::disk('web')->assertMissing('uploads/two.png');
        $this->assertSoftDeleted($product);
    }

    public function test_force_deleting_a_product_also_removes_its_image_files(): void
    {
        Storage::fake('web');
        Storage::disk('web')->put('uploads/one.png', 'x');

        $product = Product::factory()->create(['images' => ['uploads/one.png']]);
        $product->forceDelete();

        Storage::disk('web')->assertMissing('uploads/one.png');
    }

    public function test_deleting_a_product_with_no_images_does_not_error(): void
    {
        $product = Product::factory()->create(['images' => []]);

        Livewire::test(ListProducts::class)
            ->callTableAction('delete', $product)
            ->assertHasNoTableActionErrors();

        $this->assertSoftDeleted($product);
    }
}
