<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0129: two more ways to cover several product categories without
 * listing them one by one (spec 0111 D-2 is still the base rule — a row must
 * pair its category's EFFECTIVE function).
 *
 * D-1: `employment_profiles.covers_all_product_categories` — an explicit
 * wildcard on the PROFILE: true makes the user competent for ANY record
 * requiring a category, regardless of business function. Defaults false, so
 * every existing profile keeps today's per-row reading (D-10).
 *
 * D-3: `employment_product_lines.product_category_id` becomes NULLABLE — a
 * row with a null category means "every category of this row's function".
 * The column stays restrictOnDelete and part of the same unique triple (spec
 * 0111): MySQL allows several NULLs there, so two (function, null) rows are
 * a validator concern (`CompetenceLineSetValidator::DUPLICATE_PAIR_MESSAGE`),
 * never a DB-level guarantee.
 *
 * `down()` is destructive on the null-category rows (same asymmetry as every
 * other migration in this module): there is no category to invent for them,
 * so they are deleted before the column is tightened back to NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->boolean('covers_all_product_categories')->default(false)->after('is_manager');
        });

        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->foreignId('product_category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('employment_product_lines')->whereNull('product_category_id')->delete();

        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->foreignId('product_category_id')->nullable(false)->change();
        });

        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn('covers_all_product_categories');
        });
    }
};
