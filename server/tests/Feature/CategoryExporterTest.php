<?php

namespace Tests\Feature;

use App\Filament\Exports\CategoryExporter;
use App\Filament\Imports\CategoryImporter;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryExporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeExporter(): CategoryExporter
    {
        $export = Export::create([
            'file_disk' => 'local',
            'exporter' => CategoryExporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->admin()->create()->id,
        ]);

        $columnMap = array_combine(
            $columnNames = array_map(fn ($column) => $column->getName(), CategoryExporter::getColumns()),
            $columnNames,
        );

        return new CategoryExporter($export, $columnMap, []);
    }

    /**
     * The whole point of this exporter: its CSV header row (each column's
     * label) must read exactly like the importer's own downloadable
     * template (each column's example header, which defaults to its raw
     * name) — same text, same order — so a round-tripped file needs no
     * remapping.
     */
    public function test_its_header_row_matches_the_category_importers_template_header_row(): void
    {
        $importHeaders = array_map(fn ($column) => $column->getExampleHeader(), CategoryImporter::getColumns());
        $exportHeaders = array_map(fn ($column) => $column->getLabel(), CategoryExporter::getColumns());

        $this->assertSame($importHeaders, $exportHeaders);
    }

    public function test_it_exports_a_category_row_in_the_importers_format(): void
    {
        $category = Category::factory()->create([
            'slug' => 'groceries',
            'name' => 'Groceries',
            'tax_rate' => 5,
            'is_active' => true,
            'sort_order' => 3,
        ]);

        $row = ($this->makeExporter())($category);

        $this->assertSame([
            (string) $category->id, 'groceries', 'Groceries', '5.00', '1', '3',
        ], $row);
    }

    public function test_an_inactive_category_exports_as_a_literal_zero_not_blank(): void
    {
        $category = Category::factory()->create(['is_active' => false]);

        $row = ($this->makeExporter())($category);
        $isActiveIndex = array_search('is_active', array_map(
            fn ($column) => $column->getName(),
            CategoryExporter::getColumns(),
        ));

        $this->assertSame('0', $row[$isActiveIndex]);
    }
}
