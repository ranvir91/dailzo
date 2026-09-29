<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes files under public/uploads that no product image or category icon
 * references. Products/categories are looked up withTrashed() — a
 * soft-deleted row can still be restored, so its images stay "in use" until
 * it's actually gone (see Product::booted()'s forceDeleted hook).
 */
class PruneOrphanedUploads extends Command
{
    protected $signature = 'uploads:prune-orphans {--dry-run : List the orphaned files without deleting them}';

    protected $description = 'Delete uploaded files that no product image or category icon references';

    public function handle(): int
    {
        $referenced = Product::withTrashed()->pluck('images')
            ->flatten()
            ->merge(Category::withTrashed()->pluck('icon_url'))
            ->filter()
            ->map(fn (string $path) => basename($path))
            ->unique();

        $orphans = collect(Storage::disk('uploads')->files())
            // Dotfiles (.gitkeep keeping the empty dir tracked, .htaccess
            // setting the CORS header for this directory) aren't uploads.
            ->reject(fn (string $file) => str_starts_with(basename($file), '.'))
            ->reject(fn (string $file) => $referenced->contains(basename($file)));

        if ($orphans->isEmpty()) {
            $this->info('No orphaned uploads found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($orphans as $file) {
            $this->line(($dryRun ? '[dry-run] would delete ' : 'Deleting ').$file);
        }

        if (! $dryRun) {
            Storage::disk('uploads')->delete($orphans->values()->all());
        }

        $this->info(($dryRun ? 'Would remove ' : 'Removed ').$orphans->count().' orphaned file(s).');

        return self::SUCCESS;
    }
}
