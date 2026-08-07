<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "at most one quote per opportunity" policy for the product-category tree
 * (user directive 2026-08-07).
 *
 * OWNED BY THE ROOT of each branch, same shape as `management_mode` and
 * `requires_quote`: only a category without a parent authors it, every
 * descendant carries the root's value verbatim, and the column is
 * denormalised onto every row rather than resolved by a walk at read time —
 * SingleQuotePerOpportunityInheritance is the single writer that keeps the
 * subtree aligned.
 *
 * Defaults to false: the pre-existing, unconstrained behaviour, so no
 * existing category or opportunity changes behaviour at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('single_quote_per_opportunity')->default(false)->after('management_mode');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('single_quote_per_opportunity');
        });
    }
};
