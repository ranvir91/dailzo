<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // order_number/user_number existed only because UUID primary keys
    // weren't human-friendly — now that ids are plain auto-increment
    // integers (see the UUID -> int migration), the id itself is the
    // "order number"/"user number" everywhere: API, Atlas, Octa, and both
    // Flutter apps. vendor_number got the same treatment right after —
    // see drop_vendor_number_column.
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['order_number']);
            $table->dropColumn('order_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['user_number']);
            $table->dropColumn('user_number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedInteger('order_number')->nullable()->unique();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('user_number')->nullable()->unique();
        });
    }
};
