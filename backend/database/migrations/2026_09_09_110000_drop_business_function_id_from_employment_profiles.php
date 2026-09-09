<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0111 (D-1): the single business function on the employment profile
 * disappears — the `employment_product_lines` rows created by the previous
 * migration are the only source of a user's functions. The column lives on
 * an ALREADY COMMITTED table (`2026_07_04_110000_create_employment_profiles_table`),
 * hence a new migration rather than an edit of that one (backend.md §3).
 *
 * No backfill: a function alone cannot form a row (a row needs the paired
 * category, which the dropped column never carried), so `down()` restores
 * the column — nullable, same FK, same position, right after `reports_to_id`
 * — but not its values. Structure is reversible, its rows are not (same
 * asymmetry `move_employment_operational_site_to_pivot` declares).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_function_id');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->foreignId('business_function_id')->nullable()
                ->after('reports_to_id')
                ->constrained('business_functions')
                ->nullOnDelete();
        });
    }
};
