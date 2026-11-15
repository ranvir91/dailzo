<?php

namespace Tests\Feature;

use App\Filament\Imports\CategoryImporter;
use App\Models\Category;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryImporterTest extends TestCase
{
    use RefreshDatabase;

    private function makeImporter(): CategoryImporter
    {
        $import = Import::create([
            'file_name' => 'categories.csv',
            'file_path' => 'imports/categories.csv',
            'importer' => CategoryImporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->admin()->create()->id,
        ]);

        $columnMap = array_combine(
            $columnNames = array_map(fn ($column) => $column->getName(), CategoryImporter::getColumns()),
            $columnNames,
        );

        return new CategoryImporter($import, $columnMap, []);
    }

    private function blankRow(array $overrides = []): array
    {
        return array_merge([
            'id' => '',
            'slug' => '',
            'name' => '',
            'tax_rate' => '',
            'is_active' => '',
            'sort_order' => '',
        ], $overrides);
    }

    public function test_it_updates_an_existing_category_matched_by_id(): void
    {
        $category = Category::factory()->create(['name' => 'Old', 'tax_rate' => 0, 'sort_order' => 0]);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $category->id,
            'name' => 'Groceries',
            'tax_rate' => '5',
            'sort_order' => '2',
        ]));

        $category->refresh();
        $this->assertSame('Groceries', $category->name);
        $this->assertSame('5.00', (string) $category->tax_rate);
        $this->assertSame(2, $category->sort_order);
    }

    public function test_blank_cells_do_not_overwrite_existing_values(): void
    {
        $category = Category::factory()->create(['name' => 'Keep me', 'is_active' => true]);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $category->id,
            'tax_rate' => '12',
        ]));

        $category->refresh();
        $this->assertSame('Keep me', $category->name);
        $this->assertTrue($category->is_active);
        $this->assertSame('12.00', (string) $category->tax_rate);
    }

    public function test_it_fails_the_row_when_the_id_matches_no_category(): void
    {
        $this->expectException(RowImportFailedException::class);

        ($this->makeImporter())($this->blankRow(['id' => '999999']));
    }

    public function test_a_blank_id_creates_a_new_category_instead_of_matching_one(): void
    {
        $countBefore = Category::count();

        ($this->makeImporter())($this->blankRow([
            'slug' => 'new-category',
            'name' => 'New Category',
            'tax_rate' => '18',
        ]));

        $this->assertSame($countBefore + 1, Category::count());
        $category = Category::where('slug', 'new-category')->firstOrFail();
        $this->assertSame('New Category', $category->name);
        $this->assertSame('18.00', (string) $category->tax_rate);
        $this->assertTrue($category->is_active);
    }

    public function test_creating_a_new_category_requires_name_and_slug(): void
    {
        $this->expectException(ValidationException::class);

        ($this->makeImporter())($this->blankRow(['tax_rate' => '10']));
    }

    public function test_slug_must_be_unique_when_creating_a_new_category(): void
    {
        Category::factory()->create(['slug' => 'dup-slug']);

        $this->expectException(ValidationException::class);

        ($this->makeImporter())($this->blankRow([
            'slug' => 'dup-slug',
            'name' => 'Another Category',
        ]));
    }

    public function test_updating_a_category_can_keep_its_own_slug_without_a_uniqueness_conflict(): void
    {
        $category = Category::factory()->create(['slug' => 'keep-slug']);

        ($this->makeImporter())($this->blankRow([
            'id' => (string) $category->id,
            'slug' => 'keep-slug',
            'tax_rate' => '7',
        ]));

        $this->assertSame('7.00', (string) $category->refresh()->tax_rate);
    }
}
