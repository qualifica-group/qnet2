<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `old_id` to `vat_rates` (spec 0013 — external data migration): the
 * external system's id for a row migrated from it, used by the import engine
 * to skip re-imports (idempotence per old_id) and to resolve relational
 * references ("remap") — notably `products.vat_rate_id`, which ProductsSource
 * had to drop while this column did not exist. NULL for native qnet rows;
 * unique among migrated rows. Purely additive: no existing column is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('old_id')->nullable()->after('id');
            $table->unique('old_id');
        });
    }

    public function down(): void
    {
        Schema::table('vat_rates', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->dropColumn('old_id');
        });
    }
};
