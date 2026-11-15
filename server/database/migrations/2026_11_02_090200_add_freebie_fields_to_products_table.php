<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freebie-ness moves onto the product itself (no separate Deal resource to
 * manage) — each product can carry its own unlock threshold and freebie
 * price. How MANY freebie units a customer may add per order is a single
 * store-wide cap instead (store_settings.max_freebie_quantity_per_order —
 * see the companion migration), not per-product: capping per product let a
 * customer pick up 1-of-A *and* 1-of-B when only one freebie total was
 * intended, which is the bug this whole change fixes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_freebie')->default(false)->after('is_active');
            $table->decimal('freebie_price', 10, 2)->default(1)->after('is_freebie');
            $table->decimal('freebie_min_order_value', 10, 2)->default(0)->after('freebie_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_freebie', 'freebie_price', 'freebie_min_order_value']);
        });
    }
};
