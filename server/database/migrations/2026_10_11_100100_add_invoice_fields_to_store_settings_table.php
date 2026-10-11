<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller details printed on every generated invoice (app/Services/InvoiceService).
 * business_state drives the CGST+SGST vs IGST split against the customer's
 * delivery address state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->string('business_name')->nullable()->after('app_version');
            $table->text('business_address')->nullable()->after('business_name');
            $table->string('business_state')->nullable()->after('business_address');
            $table->string('business_pincode')->nullable()->after('business_state');
            $table->string('gstin')->nullable()->after('business_pincode');
            $table->string('pan')->nullable()->after('gstin');
            $table->string('fssai_license')->nullable()->after('pan');
            $table->string('cin')->nullable()->after('fssai_license');
        });
    }

    public function down(): void
    {
        Schema::table('store_settings', function (Blueprint $table) {
            $table->dropColumn([
                'business_name', 'business_address', 'business_state', 'business_pincode',
                'gstin', 'pan', 'fssai_license', 'cin',
            ]);
        });
    }
};
