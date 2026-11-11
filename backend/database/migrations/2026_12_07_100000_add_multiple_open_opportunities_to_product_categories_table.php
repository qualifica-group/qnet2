<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Several open opportunities per anagrafica" policy of a product-category
 * branch (user request 2026-11-11): when true, an open opportunity of the
 * branch neither blocks nor is blocked by the one-open-opportunity rule
 * (RegistryOpenOpportunityGuard), while the branch keeps its own management
 * mode, so its offers may still carry several rows.
 *
 * OWNED BY THE ROOT and denormalised onto every descendant by
 * MultipleOpenOpportunitiesInheritance, same shape as `multi_quote_work_order`.
 * Seeded from the rule in force until now (user decision 2026-11-11): the
 * `single`-mode categories were exempt from the block, so they start with the
 * flag on and every other category with it off; from then on the flag alone
 * decides and is edited by hand. A fresh category defaults to false.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('multiple_open_opportunities')->default(false)->after('usage_area');
        });

        // `management_mode` is already mirrored on every descendant, so the
        // backfill keeps the flag aligned with each branch root.
        DB::table('product_categories')
            ->where('management_mode', 'single')
            ->update(['multiple_open_opportunities' => true]);
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('multiple_open_opportunities');
        });
    }
};
