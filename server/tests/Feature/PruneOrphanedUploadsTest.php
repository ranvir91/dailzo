<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneOrphanedUploadsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_files_no_product_or_category_references(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('in-use.png', 'x');
        Storage::disk('uploads')->put('orphan.png', 'x');

        Product::factory()->create(['images' => ['uploads/in-use.png']]);

        $this->artisan('uploads:prune-orphans')->assertSuccessful();

        Storage::disk('uploads')->assertMissing('orphan.png');
        Storage::disk('uploads')->assertExists('in-use.png');
    }

    public function test_dry_run_reports_but_does_not_delete(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('orphan.png', 'x');

        $this->artisan('uploads:prune-orphans --dry-run')->assertSuccessful();

        Storage::disk('uploads')->assertExists('orphan.png');
    }

    public function test_dotfiles_are_never_touched(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('.htaccess', 'Header set Access-Control-Allow-Origin "*"');
        Storage::disk('uploads')->put('.gitkeep', '');

        $this->artisan('uploads:prune-orphans')->assertSuccessful();

        Storage::disk('uploads')->assertExists('.htaccess');
        Storage::disk('uploads')->assertExists('.gitkeep');
    }

    public function test_a_soft_deleted_products_images_are_still_treated_as_in_use(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('still-needed.png', 'x');

        $product = Product::factory()->create(['images' => ['uploads/still-needed.png']]);
        $product->delete();

        $this->artisan('uploads:prune-orphans')->assertSuccessful();

        Storage::disk('uploads')->assertExists('still-needed.png');
    }

    public function test_a_category_icon_counts_as_in_use_too(): void
    {
        Storage::fake('uploads');
        Storage::disk('uploads')->put('icon.png', 'x');
        Storage::disk('uploads')->put('orphan.png', 'x');

        Category::factory()->create(['icon_url' => 'uploads/icon.png']);

        $this->artisan('uploads:prune-orphans')->assertSuccessful();

        Storage::disk('uploads')->assertExists('icon.png');
        Storage::disk('uploads')->assertMissing('orphan.png');
    }
}
