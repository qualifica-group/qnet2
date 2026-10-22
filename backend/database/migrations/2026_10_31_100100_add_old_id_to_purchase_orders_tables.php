<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * External-migration anchors for the legacy ODA (spec 0296): the nullable +
 * unique `old_id` on the order (idempotence: an order already migrated is
 * skipped) and on its lines (D-6: the future supplier invoice source links
 * its lines to them by legacy `proformforas` id). Added as its own migration
 * because the create migrations are already committed (backend.md section 3).
 */
return new class extends Migration
{
    private const array TABLES = ['purchase_orders', 'purchase_order_lines'];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('old_id')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique(['old_id']);
                $table->dropColumn('old_id');
            });
        }
    }
};
