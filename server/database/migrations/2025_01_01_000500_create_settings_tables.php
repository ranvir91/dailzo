<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row table (was StoreSetting). The API always reads/updates the first row.
        Schema::create('store_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('delivery_charges', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('min_order_value', 10, 2)->default(0);
            $table->boolean('min_order_value_enabled')->default(false);
            $table->boolean('maintenance_mode')->default(false);
            $table->string('app_version');
            $table->string('store_open_time');
            $table->string('store_close_time');
            $table->json('payment_methods');
            $table->timestamps();
        });

        Schema::create('service_pincodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('pincode')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('pincode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pincodes');
        Schema::dropIfExists('store_settings');
    }
};
