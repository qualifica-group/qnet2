<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `product_categories.is_reportable` becomes the node's OWN override of an
 * inherited flag (user directive 2026-09-18): null = inherit the parent's
 * effective value (false at a root), true/false = forced on this node.
 * ReportableInheritance resolves the effective value.
 *
 * Backfill: every false becomes null. Before this migration a false node under
 * a reportable ancestor was already a report row (its subtree was included),
 * so "inherit" keeps the report identical at deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_reportable')->nullable()->default(null)->change();
        });

        DB::table('product_categories')->where('is_reportable', false)->update(['is_reportable' => null]);
    }

    public function down(): void
    {
        DB::table('product_categories')->whereNull('is_reportable')->update(['is_reportable' => false]);

        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_reportable')->nullable(false)->default(false)->change();
        });
    }
};
