<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retiring the separate Deal resource — freebies are now a per-product
 * attribute (see add_freebie_fields_to_products_table) instead of a
 * standalone grouping with its own multi-select admin screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('deal_products');
        Schema::dropIfExists('deals');
    }

    public function down(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->decimal('deal_price', 10, 2)->default(1);
            $table->unsignedInteger('max_quantity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['deal_id', 'product_id']);
        });
    }
};
