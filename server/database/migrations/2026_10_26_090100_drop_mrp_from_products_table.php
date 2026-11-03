<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `price` is now explicitly the MRP shown to customers (relabeled in Atlas
 * and the apps), with `discounted_price` the actual selling price when one
 * is set — the same two-field design the product already had before a
 * separate `mrp` column was added. That standalone field was redundant, so
 * it's removed here.
 *
 * order_items.mrp (added alongside this) is NOT touched — it's kept and now
 * snapshots products.price at order time instead, so invoices still show a
 * frozen per-line MRP. See OrderController::store().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('mrp');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('mrp', 10, 2)->nullable()->after('price');
        });
    }
};
