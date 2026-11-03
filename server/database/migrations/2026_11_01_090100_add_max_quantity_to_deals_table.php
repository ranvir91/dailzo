<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How many units of a deal product a customer may add at deal_price in one
 * order (admin-configurable — "1 or more" per the owner's request). Above
 * this, the whole line reverts to normal pricing — see
 * OrderController::applyDealPricing, which never splits one product into a
 * deal-priced portion plus a normal-priced portion within the same order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->unsignedInteger('max_quantity')->default(1)->after('deal_price');
        });
    }

    public function down(): void
    {
        Schema::table('deals', function (Blueprint $table) {
            $table->dropColumn('max_quantity');
        });
    }
};
