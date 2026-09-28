<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `products.old_source` (spec 0174, D-4): the legacy TABLE a migrated product
 * came from. Products are now imported from five legacy tables (`services`
 * through the `products` source, plus the four cost tables through
 * `cost-products`) whose ids overlap, so `old_id` alone no longer identifies
 * a row: uniqueness moves to (old_source, old_id). Rows already migrated can
 * only come from `services` and are backfilled to it.
 */
return new class extends Migration
{
    private const string SERVICES_SOURCE = 'services';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('old_source', 32)->nullable()->after('old_id');
        });

        DB::table('products')->whereNotNull('old_id')->update(['old_source' => self::SERVICES_SOURCE]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['old_id']);
            $table->unique(['old_source', 'old_id']);
        });
    }

    /**
     * A single-column unique `old_id` cannot hold the overlapping ids of the
     * cost tables, so their `old_id` is cleared before it comes back: those
     * rows stay as plain products, only their import anchor is lost.
     */
    public function down(): void
    {
        DB::table('products')
            ->whereNotNull('old_source')
            ->where('old_source', '!=', self::SERVICES_SOURCE)
            ->update(['old_id' => null]);

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['old_source', 'old_id']);
            $table->dropColumn('old_source');
            $table->unique('old_id');
        });
    }
};
