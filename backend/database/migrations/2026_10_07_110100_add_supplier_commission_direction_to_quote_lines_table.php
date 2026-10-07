<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `quote_lines.supplier_commission_direction` (spec 0202, D-3/D-7): the
 * Supplier commission direction (RECEIVED|PAID) frozen onto a REVENUE row
 * when it is created (or its product changes), mirroring the unit of measure
 * snapshot of spec 0088. NULL = the Supplier commission is not calculated;
 * COST rows are always NULL.
 *
 * Backfill (D-10): every existing REVENUE row copies the direction its
 * product's typology currently carries (set by the previous migration). Plain
 * query builder, no amount or `margin_net` is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->string('supplier_commission_direction', 16)->nullable()->after('unit_of_measure_id');
        });

        foreach (['RECEIVED', 'PAID'] as $direction) {
            DB::table('quote_lines')
                ->where('line_type', 'REVENUE')
                ->whereIn('product_id', function ($query) use ($direction): void {
                    $query->select('products.id')
                        ->from('products')
                        ->join('product_typologies', 'product_typologies.id', '=', 'products.product_typology_id')
                        ->where('product_typologies.supplier_commission_enabled', true)
                        ->where('product_typologies.supplier_commission_direction', $direction);
                })
                ->update(['supplier_commission_direction' => $direction]);
        }
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->dropColumn('supplier_commission_direction');
        });
    }
};
