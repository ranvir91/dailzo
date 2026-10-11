<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Tax applies per category (food = 0%, clothes = 5%, electronics = 18%,
    // ...), not per product — set once on the category, every product in it
    // inherits the rate. Product prices stay tax-inclusive, as already
    // entered; this rate is only used to split a price into its base +
    // tax components for display and for invoices.
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('tax_rate', 5, 2)->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
