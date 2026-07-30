<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "This category is quoted" flag on the product-category tree.
 *
 * The flag is OWNED BY THE ROOT of each branch: only a category without a
 * parent can set it, and every descendant carries the root's value. The
 * column is therefore present (and kept in sync) on EVERY row rather than
 * resolved by walking `parent_id` at read time — so the grid, the filters,
 * the export and the for-select paths all read a real column with no
 * per-row query. RequiresQuoteInheritance is the single writer that keeps
 * the subtree aligned; nothing else may write this column.
 *
 * Defaults to false: no existing category is retroactively marked as quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('requires_quote')->default(false)->after('business_function_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('requires_quote');
        });
    }
};
