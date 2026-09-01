<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `products.unit_of_measure_id` (spec 0088, D-4): NOT NULL, restrictOnDelete
 * — a Product always has an Unita di Misura, defaulting server-side
 * (ProductService) to the `code='unit'` row created by the previous
 * migration. Same three-step shape as
 * 2026_07_29_100000_add_code_to_products_table.php (add nullable -> backfill
 * -> tighten to NOT NULL), so it never fails against pre-existing rows on
 * either MySQL (prod) or SQLite (dev/test): (1) add the column nullable,
 * unconstrained yet; (2) backfill every existing product with the default
 * unit's id via plain query-builder updates; (3) tighten to NOT NULL and add
 * the FK once every row is populated.
 */
return new class extends Migration
{
    private const string DEFAULT_UNIT_CODE = 'unit';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_of_measure_id')->nullable()->after('state_id');
        });

        $this->backfillExistingRows();

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('unit_of_measure_id')->nullable(false)->change();
            $table->foreign('unit_of_measure_id')->references('id')->on('units_of_measure')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['unit_of_measure_id']);
            $table->dropColumn('unit_of_measure_id');
        });
    }

    private function backfillExistingRows(): void
    {
        $defaultUnitId = DB::table('units_of_measure')->where('code', self::DEFAULT_UNIT_CODE)->value('id');

        DB::table('products')->update(['unit_of_measure_id' => $defaultUnitId]);
    }
};
