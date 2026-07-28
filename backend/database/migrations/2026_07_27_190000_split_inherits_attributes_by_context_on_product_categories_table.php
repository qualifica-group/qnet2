<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the single `inherits_attributes` barrier into ONE FLAG PER USAGE
 * CONTEXT (spec 0061 follow-up): a category can now keep inheriting its
 * ancestors' Opportunity attributes while cutting itself off from their
 * Product ones, and vice versa. Both columns are backfilled from the old flag,
 * so every existing category keeps its current behaviour in both contexts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_product_attributes')->default(true)->after('parent_id');
            $table->boolean('inherits_opportunity_attributes')->default(true)->after('inherits_product_attributes');
        });

        DB::table('product_categories')->update([
            'inherits_product_attributes' => DB::raw('inherits_attributes'),
            'inherits_opportunity_attributes' => DB::raw('inherits_attributes'),
        ]);

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('inherits_attributes');
        });
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('inherits_attributes')->default(true)->after('parent_id');
        });

        // The pre-split flag governed both contexts at once and Opportunity was
        // its only consumer before spec 0061, so that side wins on the way back.
        DB::table('product_categories')->update([
            'inherits_attributes' => DB::raw('inherits_opportunity_attributes'),
        ]);

        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn(['inherits_product_attributes', 'inherits_opportunity_attributes']);
        });
    }
};
