<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selling price per supplier (spec 0320 rev. 2, D-14): next to `cost`, both
 * NOT NULL default 0 — the form shows only the one the product's usages need,
 * the other stays 0. Existing rows take the product's current price (its
 * "standard price" from now on, D-15), 0 when the product had none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->decimal('price', 15, 2)->default(0)->after('supplier_id');
            $table->decimal('cost', 15, 2)->default(0)->change();
        });

        DB::table('product_suppliers')->update([
            'price' => DB::raw('COALESCE((SELECT products.price FROM products WHERE products.id = product_suppliers.product_id), 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('product_suppliers', function (Blueprint $table) {
            $table->dropColumn('price');
            $table->decimal('cost', 15, 2)->change();
        });
    }
};
