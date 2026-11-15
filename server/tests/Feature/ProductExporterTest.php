<?php

namespace Tests\Feature;

use App\Filament\Exports\ProductExporter;
use App\Filament\Imports\ProductImporter;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductExporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeExporter(): ProductExporter
    {
        $export = Export::create([
            'file_disk' => 'local',
            'exporter' => ProductExporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->admin()->create()->id,
        ]);

        $columnMap = array_combine(
            $columnNames = array_map(fn ($column) => $column->getName(), ProductExporter::getColumns()),
            $columnNames,
        );

        return new ProductExporter($export, $columnMap, []);
    }

    /**
     * The whole point of this exporter: its CSV header row (each column's
     * label) must read exactly like the importer's own downloadable
     * template (each column's example header, which defaults to its raw
     * name) — same text, same order — so a round-tripped file needs no
     * remapping.
     */
    public function test_its_header_row_matches_the_product_importers_template_header_row(): void
    {
        $importHeaders = array_map(fn ($column) => $column->getExampleHeader(), ProductImporter::getColumns());
        $exportHeaders = array_map(fn ($column) => $column->getLabel(), ProductExporter::getColumns());

        $this->assertSame($importHeaders, $exportHeaders);
    }

    public function test_it_exports_a_product_row_in_the_importers_format(): void
    {
        $category = Category::factory()->create(['slug' => 'groceries']);
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'sku' => 'CHG-001',
            'name' => 'Mobile Charger',
            'price' => 1200,
            'discounted_price' => 999,
            'stock' => 50,
            'is_active' => true,
            'unit' => 'PCS',
            'variant_label' => null,
        ]);

        $row = ($this->makeExporter())($product);

        $this->assertSame([
            (string) $product->id, 'CHG-001', 'Mobile Charger', '1200.00', '999.00', '50', '1', 'PCS', null, 'groceries', 'Acme',
        ], $row);
    }

    public function test_an_inactive_product_exports_as_a_literal_zero_not_blank(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $row = ($this->makeExporter())($product);
        $isActiveIndex = array_search('is_active', array_map(
            fn ($column) => $column->getName(),
            ProductExporter::getColumns(),
        ));

        $this->assertSame('0', $row[$isActiveIndex]);
    }
}
