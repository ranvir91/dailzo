<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AtlasProductResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_creating_a_product_saves_the_new_billing_fields(): void
    {
        $category = Category::factory()->create(['tax_rate' => 18]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'item_type' => 'GOODS',
                'name' => 'Mobile Charger',
                'category_id' => $category->id,
                'item_code' => 'IC-9',
                'sku' => 'CHG-001',
                'hsn_sac_code' => '8504',
                'brand' => 'Acme',
                'unit' => 'PCS',
                'price' => 1200,
                'mrp' => 1500,
                'purchase_price' => 900,
                'stock' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'CHG-001')->firstOrFail();
        $this->assertSame('IC-9', $product->item_code);
        $this->assertSame('8504', $product->hsn_sac_code);
        $this->assertSame('Acme', $product->brand);
        $this->assertSame('PCS', $product->unit);
        $this->assertSame('1500.00', (string) $product->mrp);
        $this->assertSame('900.00', (string) $product->purchase_price);
    }

    public function test_creating_a_product_saves_its_tags(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Combo Pack',
                'category_id' => $category->id,
                'price' => 199,
                'stock' => 5,
                'tags' => ['BOGO', 'Combo'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Combo Pack')->firstOrFail();
        $this->assertSame(['BOGO', 'Combo'], $product->tags);
    }

    public function test_editing_a_product_updates_the_new_billing_fields(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'sku' => 'OLD-SKU']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['sku' => 'NEW-SKU', 'brand' => 'Updated Brand'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('NEW-SKU', $product->refresh()->sku);
        $this->assertSame('Updated Brand', $product->brand);
    }

    public function test_product_sku_must_be_unique(): void
    {
        $category = Category::factory()->create();
        Product::factory()->create(['category_id' => $category->id, 'sku' => 'DUP-1']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Another Product',
                'category_id' => $category->id,
                'sku' => 'DUP-1',
                'price' => 10,
                'stock' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku']);
    }

    public function test_category_tax_rate_is_saved_and_listed(): void
    {
        Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->setActionData([
                'name' => 'Electronics',
                'slug' => 'electronics',
                'tax_rate' => 18,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $category = Category::where('slug', 'electronics')->firstOrFail();
        $this->assertSame('18.00', (string) $category->tax_rate);
    }

    public function test_category_tax_rate_accepts_a_decimal_value(): void
    {
        Livewire::test(ManageCategories::class)
            ->mountAction('create')
            ->setActionData([
                'name' => 'Textiles',
                'slug' => 'textiles',
                'tax_rate' => 12.5,
            ])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $category = Category::where('slug', 'textiles')->firstOrFail();
        $this->assertSame('12.50', (string) $category->tax_rate);
    }
}
