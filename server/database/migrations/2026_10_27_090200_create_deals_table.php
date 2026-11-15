<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ₹1-style deal: once the rest of the customer's cart (every item not
 * itself part of a deal) reaches min_order_value, every linked product
 * (deal_products) becomes addable at deal_price — capped to 1 unit per
 * order, enforced server-side in OrderController::applyDealPricing().
 * Deliberately separate from `offers` (banners/tag links) — different
 * purpose, different admin screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->decimal('deal_price', 10, 2)->default(1);
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

    public function down(): void
    {
        Schema::dropIfExists('deal_products');
        Schema::dropIfExists('deals');
    }
};
