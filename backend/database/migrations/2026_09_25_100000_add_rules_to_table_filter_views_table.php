<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Custom filter rules (spec 0158): a saved TableFilterView may carry a
 * generic E/O rule set — `{and: Rule[], or: Rule[]}` — instead of (or on top
 * of) the plain AG Grid `filters`/`advanced_filters` it already stores. A
 * view with `rules` present is a "custom filter" view: `filters`/
 * `advanced_filters` are saved empty for it (TableFilterViewService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('table_filter_views', function (Blueprint $table) {
            $table->json('rules')->nullable()->after('advanced_filters');
        });
    }

    public function down(): void
    {
        Schema::table('table_filter_views', function (Blueprint $table) {
            $table->dropColumn('rules');
        });
    }
};
