<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Coupons were never actually applied server-side: the client computed
    // its own discount and sent a final `total`, so nothing recorded which
    // coupon (if any) an order used, and per_user_limit/usage_limit were
    // never enforced. This links orders back to the coupon they used and
    // lets a per-user usage count be computed from coupon_usages.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignUuid('coupon_id')->nullable()->after('total')
                ->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('coupon_id');
        });

        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->foreignUuid('order_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });
    }
};
