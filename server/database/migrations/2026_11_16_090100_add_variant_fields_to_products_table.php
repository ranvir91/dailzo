<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pack-size/weight variants (e.g. Aata 5kg/10kg/20kg) are modeled as
 * ordinary products linked to a "parent" product via parent_id, each with
 * its own price/stock/sku — not a separate variants table. This reuses
 * every existing piece (cart, checkout, orders, tax, freebies) unchanged,
 * since a variant IS a normal, independently orderable product; parent_id +
 * variant_label only group them for display (ProductController::show()
 * embeds a parent's variants so the app can render a pack-size picker) and
 * hide children from the main catalog listing (ProductController::index()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('unit')
                ->constrained('products')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('variant_label');
        });
    }
};
