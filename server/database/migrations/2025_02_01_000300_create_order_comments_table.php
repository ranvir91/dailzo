<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Delivery-partner incident notes ("Customer not at delivery address", ...)
    // and a record of status-change comments, shown to admins on the order.
    public function up(): void
    {
        Schema::create('order_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('delivery_partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('INCIDENT'); // INCIDENT | STATUS_CHANGE | REASSIGNMENT
            $table->text('body');
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_comments');
    }
};
