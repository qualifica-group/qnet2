<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product attribute VALUES (spec 0061), mirroring
 * `opportunities.attribute_values`: dynamic-field values collected per
 * Product, keyed by Attribute `code` — the PRODUCT-context effective
 * attributes of the product's own category (App\Products\ProductAttributeResolver).
 * Written EXCLUSIVELY by ProductService, never mass-assignable (deliberately
 * absent from Product's #[Fillable]).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('attribute_values')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('attribute_values');
        });
    }
};
