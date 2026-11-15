<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An offer can optionally be a "deal": once the rest of the customer's cart
 * (every item not itself part of a deal) reaches `min_order_value`, every
 * linked product (offer_products) becomes addable at `deal_price` instead of
 * its normal price — capped to 1 unit per order, enforced server-side in
 * OrderController::store(). See InvoiceService/OrderController for how the
 * MRP-vs-charged-price snapshot already makes this show correctly on
 * invoices without any extra plumbing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->boolean('is_deal')->default(false)->after('tag');
            $table->decimal('min_order_value', 10, 2)->default(0)->after('is_deal');
            $table->decimal('deal_price', 10, 2)->default(1)->after('min_order_value');
        });

        Schema::create('offer_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['offer_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_products');

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['is_deal', 'min_order_value', 'deal_price']);
        });
    }
};
