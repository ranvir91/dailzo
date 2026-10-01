<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Moves delivery-partner login onto the users table (role = DELIVERY_PARTNER),
     * same as every other account kind. delivery_partners keeps its own id and
     * becomes a profile table — everything that already points at it
     * (delivery_assignments, order_comments) is untouched.
     */
    public function up(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('id');
            $table->unsignedBigInteger('vendor_id')->nullable()->after('user_id');
        });

        // Backfill: give every existing delivery partner a paired users row.
        // Raw query builder throughout — `password` here is already the bcrypt
        // hash stored in the DB (the model casts it, the query builder doesn't),
        // so copying it as-is doesn't re-hash it and the partner's existing
        // password keeps working unchanged.
        $now = now();
        DB::table('delivery_partners')->orderBy('id')->get()->each(function ($partner) use ($now) {
            $userId = DB::table('users')->insertGetId([
                'name' => $partner->name,
                'phone' => $partner->phone,
                'role' => 'DELIVERY_PARTNER',
                'password' => $partner->password,
                'created_at' => $partner->created_at ?? $now,
                'updated_at' => $now,
            ]);

            DB::table('delivery_partners')->where('id', $partner->id)->update(['user_id' => $userId]);
        });

        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->nullOnDelete();
            $table->index('vendor_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_partners', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropColumn(['user_id', 'vendor_id']);
        });
    }
};
