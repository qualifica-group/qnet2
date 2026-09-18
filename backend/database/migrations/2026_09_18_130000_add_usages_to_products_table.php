<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `products.usages` (spec 0142, D-2/D-3): the set of App\Enums\ProductUsage
 * values deciding which Offerta tab may pick the product. Existing products
 * are backfilled to Sellable only (user decision); new rows get the same
 * default from the Product model's own attribute default.
 */
return new class extends Migration
{
    private const string DEFAULT_USAGES = '["SALE"]';

    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('usages')->nullable()->after('product_type');
        });

        DB::table('products')->update(['usages' => self::DEFAULT_USAGES]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('usages');
        });
    }
};
