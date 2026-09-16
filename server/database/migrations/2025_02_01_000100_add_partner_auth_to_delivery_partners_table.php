<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Lets a DeliveryPartner authenticate (phone + password) in the delivery
    // partner mobile app, the same way User does for customers/admins.
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->string('password')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('password');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropColumn(['password', 'is_active', 'remember_token']);
        });
    }
};
