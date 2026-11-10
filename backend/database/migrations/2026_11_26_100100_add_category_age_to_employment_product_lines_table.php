<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0367: on a competence row with no category ("Tutte"), which part of
 * the function's catalogue it covers — the new categories, the legacy ones
 * ([OLD], `product_categories.is_legacy`) or both. Both default true, so every
 * existing row keeps covering the whole function. A row on a specific
 * category always carries both true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->boolean('covers_current_categories')->default(true)->after('product_category_id');
            $table->boolean('covers_legacy_categories')->default(true)->after('covers_current_categories');
        });
    }

    public function down(): void
    {
        Schema::table('employment_product_lines', function (Blueprint $table): void {
            $table->dropColumn(['covers_current_categories', 'covers_legacy_categories']);
        });
    }
};
