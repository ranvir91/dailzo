<?php

namespace Tests\Feature;

use App\Filament\Imports\ProductImporter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(): ProductImporter
    {
        $import = Import::create([
            'file_name' => 'products.csv',
            'file_path' => 'imports/products.csv',
            'importer' => ProductImporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->admin()->create()->id,
        ]);

        $columnMap = array_combine(
            $columnNames = array_map(fn ($column) => $column->getName(), ProductImporter::getColumns()),
            $columnNames,
        );

        return new ProductImporter($import, $columnMap, []);
    }

    private function blankRow(array $overrides = []): array
    {
        return array_merge([
            'id' => '',
            'sku' => '',
            'name' => '',
            'price' => '',
            'discounted_price' => '',
            'stock' => '',
            'is_active' => '',
            'unit' => '',
            'variant_label' => '',
            'category' => '',
            'brand' => '',
        ], $overrides);
    }

    public function test_it_updates_an_existing_product_matched_by_id(): void
    {
        $product = Product::factory()->create(['name' => 'Old name', 'price' => 100, 'stock' => 5]);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $product->id,
            'name' => 'New name',
            'price' => '150',
            'stock' => '20',
        ]));

        $product->refresh();
        $this->assertSame('New name', $product->name);
        $this->assertSame('150.00', (string) $product->price);
        $this->assertSame(20, $product->stock);
    }

    public function test_blank_cells_do_not_overwrite_existing_values(): void
    {
        $product = Product::factory()->create(['name' => 'Keep me', 'price' => 100, 'stock' => 5]);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $product->id,
            'price' => '175',
        ]));

        $product->refresh();
        $this->assertSame('Keep me', $product->name);
        $this->assertSame(5, $product->stock);
        $this->assertSame('175.00', (string) $product->price);
    }

    public function test_category_and_brand_columns_resolve_by_slug_and_name(): void
    {
        $product = Product::factory()->create();
        $category = Category::factory()->create(['slug' => 'groceries']);
        $brand = Brand::factory()->create(['name' => 'Acme']);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $product->id,
            'category' => 'groceries',
            'brand' => 'Acme',
        ]));

        $product->refresh();
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
    }

    public function test_it_fails_the_row_when_the_id_matches_no_product(): void
    {
        $this->expectException(RowImportFailedException::class);

        ($this->makeImporter())($this->blankRow(['id' => '999999']));
    }

    public function test_a_blank_id_creates_a_new_product_instead_of_matching_one(): void
    {
        $category = Category::factory()->create(['slug' => 'groceries']);
        $countBefore = Product::count();

        ($this->makeImporter())($this->blankRow([
            'sku' => 'NEW-001',
            'name' => 'Brand New Product',
            'price' => '499',
            'stock' => '10',
            'category' => 'groceries',
        ]));

        $this->assertSame($countBefore + 1, Product::count());
        $product = Product::where('sku', 'NEW-001')->firstOrFail();
        $this->assertSame('Brand New Product', $product->name);
        $this->assertSame('499.00', (string) $product->price);
        $this->assertSame(10, $product->stock);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame([], $product->images);
    }

    public function test_creating_a_new_product_defaults_optional_fields(): void
    {
        $category = Category::factory()->create();

        ($this->makeImporter())($this->blankRow([
            'sku' => 'NEW-002',
            'name' => 'Minimal Product',
            'price' => '100',
            'category' => $category->slug,
        ]));

        $product = Product::where('sku', 'NEW-002')->firstOrFail();
        $this->assertSame(0, $product->stock);
        $this->assertTrue($product->is_active);
        $this->assertSame([], $product->images);
    }

    public function test_creating_a_new_product_requires_name_price_and_category(): void
    {
        $this->expectException(ValidationException::class);

        ($this->makeImporter())($this->blankRow(['sku' => 'NEW-003']));
    }

    public function test_sku_must_be_unique_when_creating_a_new_product(): void
    {
        Product::factory()->create(['sku' => 'DUP-1']);
        $category = Category::factory()->create();

        $this->expectException(ValidationException::class);

        ($this->makeImporter())($this->blankRow([
            'sku' => 'DUP-1',
            'name' => 'Another Product',
            'price' => '100',
            'category' => $category->slug,
        ]));
    }

    public function test_updating_a_product_can_keep_its_own_sku_without_a_uniqueness_conflict(): void
    {
        $product = Product::factory()->create(['sku' => 'KEEP-1']);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $product->id,
            'sku' => 'KEEP-1',
            'price' => '200',
        ]));

        $this->assertSame('200.00', (string) $product->refresh()->price);
    }
}
