<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Snapshot of the product's category tax_rate at order time — like
    // discount_amount/delivery_charges, this must stay frozen even if the
    // category's rate changes later, so past invoices stay accurate.
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
