<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "single"|"multiple" card-line policy for the product-category tree
 * (spec 0077).
 *
 * OWNED BY THE ROOT of each branch, same shape as `requires_quote`: only a
 * category without a parent authors it, every descendant carries the root's
 * value verbatim, and the column is denormalised onto every row rather than
 * resolved by a walk at read time — CategoryManagementModeInheritance is the
 * single writer that keeps the subtree aligned. A descendant's own column is
 * therefore never authored directly and never implicitly re-derived at read
 * time; it only ever reflects what the last sync wrote.
 *
 * Defaults to "multiple" (D-8): the pre-existing, unconstrained behaviour, so
 * no existing category or card changes behaviour at deploy (AC-006).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('management_mode', 16)->default('multiple')->after('is_selectable');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('management_mode');
        });
    }
};
