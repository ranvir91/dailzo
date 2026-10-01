<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Same rationale as dropping order_number/user_number: vendor ids are
    // plain auto-increment integers now, so the id itself is the vendor
    // number everywhere — no need for a separate human-facing column.
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropUnique(['vendor_number']);
            $table->dropColumn('vendor_number');
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->unsignedInteger('vendor_number')->nullable()->unique();
        });
    }
};
