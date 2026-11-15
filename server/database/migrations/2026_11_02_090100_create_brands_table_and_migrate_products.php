<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Brand becomes a managed resource (Atlas > Catalog > Brands) instead of a
 * free-text field on the product — same pattern as Category. Existing
 * distinct `products.brand` string values are turned into real Brand rows
 * and linked via the new `brand_id` before the old string column is dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('brand_id')->nullable()->after('brand')->constrained()->nullOnDelete();
        });

        $distinctBrands = DB::table('products')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->pluck('brand');

        foreach ($distinctBrands as $name) {
            $brandId = DB::table('brands')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')->where('brand', $name)->update(['brand_id' => $brandId]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('hsn_sac_code');
        });

        DB::table('products')
            ->join('brands', 'brands.id', '=', 'products.brand_id')
            ->update(['products.brand' => DB::raw('brands.name')]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::dropIfExists('brands');
    }
};
