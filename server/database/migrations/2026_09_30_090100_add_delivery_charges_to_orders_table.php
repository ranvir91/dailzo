<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Like discount_amount, this is a snapshot of what was actually charged
    // at order time — not something to recompute from the current
    // StoreSetting::delivery_charges later, which can change.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('delivery_charges', 10, 2)->default(0)->after('discount_amount');
        });

        // Backfill existing orders: their `total` already had delivery
        // charges baked in (subtotal + delivery - discount) before this
        // column existed, so derive it rather than leaving every past
        // order showing "no delivery charge" when one may well have
        // applied.
        DB::statement(<<<'SQL'
            UPDATE orders o
            SET o.delivery_charges = GREATEST(
                o.total - COALESCE(
                    (SELECT SUM(oi.price * oi.quantity) FROM order_items oi WHERE oi.order_id = o.id),
                    0
                ) + o.discount_amount,
                0
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('delivery_charges');
        });
    }
};
