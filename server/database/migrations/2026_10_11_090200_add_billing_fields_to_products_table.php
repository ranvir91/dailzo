<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Fields added to match the billing software's "Add Item" form, for
    // GST-compliant invoicing and billing-software-style product data.
    // Deliberately not added: a "Drawing No." field (that software's own
    // field, but it's an engineering/manufacturing term with no meaning
    // for a grocery catalog — `description` already covers free-text
    // notes) and a per-product GST% (tax is set once per category instead
    // — see add_tax_rate_to_categories_table — so one rate change applies
    // to every product in it rather than needing to be re-entered per item).
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('item_type')->default('GOODS')->after('name'); // GOODS | SERVICE
            $table->string('item_code')->nullable()->after('sku');
            $table->string('hsn_sac_code')->nullable()->after('item_code');
            $table->string('brand')->nullable()->after('hsn_sac_code');
            $table->string('unit')->nullable()->after('brand'); // PCS, KG, GM, L, ML, BOX, PACK, DOZEN
            $table->decimal('mrp', 10, 2)->nullable()->after('price');
            $table->decimal('purchase_price', 10, 2)->nullable()->after('discounted_price');
            $table->string('part_no')->nullable()->after('purchase_price');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'item_type', 'item_code', 'hsn_sac_code', 'brand',
                'unit', 'mrp', 'purchase_price', 'part_no',
            ]);
        });
    }
};
