<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `products.product_typology_id` (spec 0099, D-3): NOT NULL, restrictOnDelete
 * — a Product always has a Tipologia, defaulting server-side (ProductService)
 * to the `code='institution'` row created by the previous migration.
 *
 * Same three-step shape as
 * 2026_09_01_100100_add_unit_of_measure_id_to_products_table.php (add
 * nullable -> backfill -> tighten to NOT NULL), so it never fails against
 * pre-existing rows on either MySQL (prod) or SQLite (dev/test).
 */
return new class extends Migration
{
    private const string DEFAULT_TYPOLOGY_CODE = 'institution';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_typology_id')->nullable()->after('unit_of_measure_id');
        });

        $this->backfillExistingRows();

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('product_typology_id')->nullable(false)->change();
            $table->foreign('product_typology_id')->references('id')->on('product_typologies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['product_typology_id']);
            $table->dropColumn('product_typology_id');
        });
    }

    private function backfillExistingRows(): void
    {
        $defaultTypologyId = DB::table('product_typologies')->where('code', self::DEFAULT_TYPOLOGY_CODE)->value('id');

        DB::table('products')->update(['product_typology_id' => $defaultTypologyId]);
    }
};
