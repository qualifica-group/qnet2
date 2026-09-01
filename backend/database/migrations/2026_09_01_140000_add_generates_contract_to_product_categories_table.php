<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "This branch is sold under a contract" policy for the product-category tree
 * (spec 0091, user directive 2026-09-01): when false, an offer of this branch
 * closing positively no longer opens a contract.
 *
 * OWNED BY THE ROOT of each branch, same shape as `management_mode`,
 * `requires_quote` and `single_quote_per_opportunity`: only a category without
 * a parent authors it, every descendant carries the root's value verbatim, and
 * the column is denormalised onto every row rather than resolved by a walk at
 * read time — ContractGenerationInheritance is the single writer that keeps
 * the subtree aligned.
 *
 * Defaults to TRUE (unlike its siblings, which default to the permissive
 * false): the pre-existing behaviour is that EVERY positively closed offer
 * opens a contract, so true is what leaves existing branches untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('generates_contract')->default(true)->after('single_quote_per_opportunity');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('generates_contract');
        });
    }
};
