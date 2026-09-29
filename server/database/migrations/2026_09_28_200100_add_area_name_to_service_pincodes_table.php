<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_pincodes', function (Blueprint $table) {
            $table->string('area_name')->nullable()->after('pincode');
        });
    }

    public function down(): void
    {
        Schema::table('service_pincodes', function (Blueprint $table) {
            $table->dropColumn('area_name');
        });
    }
};
