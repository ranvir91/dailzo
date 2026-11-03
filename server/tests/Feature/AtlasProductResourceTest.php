<?php

namespace Tests\Feature;

use App\Filament\Resources\CategoryResource\Pages\ManageCategories;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\Pages\ListProducts;
use App\Models\Brand;
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
        $brand = Brand::factory()->create(['name' => 'Acme']);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'item_type' => 'GOODS',
                'name' => 'Mobile Charger',
                'category_id' => $category->id,
                'item_code' => 'IC-9',
                'sku' => 'CHG-001',
                'hsn_sac_code' => '8504',
                'brand_id' => $brand->id,
                'unit' => 'PCS',
                'price' => 1200,
                'stock' => 10,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'CHG-001')->firstOrFail();
        $this->assertSame('IC-9', $product->item_code);
        $this->assertSame('8504', $product->hsn_sac_code);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('PCS', $product->unit);
        $this->assertSame('1200.00', (string) $product->price);
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
        $brand = Brand::factory()->create(['name' => 'Updated Brand']);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['sku' => 'NEW-SKU', 'brand_id' => $brand->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('NEW-SKU', $product->refresh()->sku);
        $this->assertSame($brand->id, $product->brand_id);
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

    public function test_editing_a_product_can_toggle_its_active_status(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->fillForm(['is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($product->refresh()->is_active);
    }

    public function test_bulk_activate_and_deactivate_products(): void
    {
        $category = Category::factory()->create();
        $a = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $b = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('deactivate', [$a->id, $b->id]);

        $this->assertFalse($a->refresh()->is_active);
        $this->assertFalse($b->refresh()->is_active);

        Livewire::test(ListProducts::class)
            ->callTableBulkAction('activate', [$a->id, $b->id]);

        $this->assertTrue($a->refresh()->is_active);
        $this->assertTrue($b->refresh()->is_active);
    }

    public function test_purchase_price_and_part_no_are_not_on_the_product_form(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        Livewire::test(EditProduct::class, ['record' => $product->getRouteKey()])
            ->assertFormFieldDoesNotExist('purchase_price')
            ->assertFormFieldDoesNotExist('part_no');
    }

    public function test_a_product_can_be_marked_as_a_freebie_with_its_own_price_and_threshold(): void
    {
        $category = Category::factory()->create();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'name' => 'Free Keychain',
                'category_id' => $category->id,
                'price' => 150,
                'stock' => 20,
                'is_freebie' => true,
                'freebie_price' => 1,
                'freebie_min_order_value' => 500,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'Free Keychain')->firstOrFail();
        $this->assertTrue($product->is_freebie);
        $this->assertSame('1.00', (string) $product->freebie_price);
        $this->assertSame('500.00', (string) $product->freebie_min_order_value);
    }
}
