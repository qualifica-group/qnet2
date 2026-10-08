<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier commission settings of a product typology (spec 0202, D-2/D-7):
 * `supplier_commission_enabled` switches the calculation on/off and
 * `supplier_commission_direction` (RECEIVED|PAID, null when disabled) says
 * who bears it.
 *
 * Data backfill (D-10), plain query builder so no application code is
 * needed: `institution` -> enabled + RECEIVED, every other typology ->
 * enabled + PAID, which keeps today's behaviour (the Supplier commission is
 * calculated and subtracted everywhere) unchanged. No amount is recomputed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->boolean('supplier_commission_enabled')->default(false)->after('description');
            $table->string('supplier_commission_direction', 16)->nullable()->after('supplier_commission_enabled');
        });

        DB::table('product_typologies')->update([
            'supplier_commission_enabled' => true,
            'supplier_commission_direction' => 'PAID',
        ]);

        DB::table('product_typologies')->where('code', 'institution')->update([
            'supplier_commission_direction' => 'RECEIVED',
        ]);
    }

    public function down(): void
    {
        Schema::table('product_typologies', function (Blueprint $table) {
            $table->dropColumn(['supplier_commission_enabled', 'supplier_commission_direction']);
        });
    }
};
