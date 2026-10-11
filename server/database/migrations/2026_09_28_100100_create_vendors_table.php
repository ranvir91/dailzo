<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            // The vendor's login identity — see users.role = 'VENDOR'.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Human-facing sequential id, same pattern as order_number/user_number.
            $table->unsignedInteger('vendor_number')->nullable()->unique();
            $table->string('business_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('user_id');
        });

        // Which of the platform's serviceable pincodes (service_pincodes) each
        // vendor covers. Many-to-many: a pincode can have more than one covering
        // vendor (superadmin picks which one gets a given order), and a vendor
        // can cover more than one pincode. Plain pivot table — composite primary
        // key, no separate id: nothing references a row here by its own id, and
        // a surrogate uuid id would need a Pivot model with HasUuids to ever get
        // populated (belongsToMany's sync()/attach() insert the pivot row
        // directly, bypassing model-level id generation).
        Schema::create('vendor_service_pincodes', function (Blueprint $table) {
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_pincode_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['vendor_id', 'service_pincode_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_pincodes');
        Schema::dropIfExists('vendors');
    }
};
