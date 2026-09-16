<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Mirrors `refresh_tokens` (which is FK'd to `users`) for the DeliveryPartner
    // guard, so partner sessions don't share a table/FK with customer sessions.
    public function up(): void
    {
        Schema::create('partner_refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('token')->unique();
            $table->foreignUuid('delivery_partner_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_refresh_tokens');
    }
};
