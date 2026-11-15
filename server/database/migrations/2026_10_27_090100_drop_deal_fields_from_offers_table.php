<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ₹1 deal mechanic moved off Offer onto its own `deals` table/resource —
 * offers (banners, tag links) and deals (minimum-order-value unlocks) are
 * different concepts managed in different places in Atlas, per the owner's
 * explicit call to separate them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('offer_products');

        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['is_deal', 'min_order_value', 'deal_price']);
        });
    }

    public function down(): void
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
};
