<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When set, tapping this offer's banner in the app opens the list of
 * products carrying this same tag (products.tags) — e.g. a "BOGO" offer
 * links to every product tagged "BOGO", regardless of category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->string('tag')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('tag');
        });
    }
};
