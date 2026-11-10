<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marks the categories of the legacy catalogue (user directive 2026-11-07): an
 * open opportunity whose lines all sit on them never blocks a new one
 * (RegistryOpenOpportunityGuard). A flag rather than the "[OLD] " name, so a
 * rename never changes the rule. Backfilled once here: the migrated categories
 * (an `old_id`) and the legacy cost branch ("[OLD] Costi", created by name, no
 * `old_id`); from now on the legacy migration writers set it themselves.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_legacy')->default(false)->after('is_active');
        });

        DB::table('product_categories')
            ->where(static fn ($query) => $query->whereNotNull('old_id')->orWhere('name', 'like', '[OLD] %'))
            ->update(['is_legacy' => true]);
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });
    }
};
