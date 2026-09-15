<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "This category is a row of the Gestione Richieste / Gestione Iscritti
 * report" flag (spec 0131). Owned by EACH NODE and never inherited, same
 * shape as `is_selectable`: a reportable category aggregates its whole
 * subtree, so a parent and one of its children can both be reportable.
 *
 * Defaults to false; the backfill flags the six branches the report used to
 * read from config, plus "DIL" (user directive 2026-09-15), so an
 * installation already seeded keeps its report at deploy. Bound by name to
 * QualificaCatalogSeeder::REPORTABLE_CATEGORIES.
 */
return new class extends Migration
{
    private const array INITIAL_REPORTABLE_CATEGORIES = ['GOL', 'Autoimpiego', 'Yisu', 'Autofinanziato', 'DIL', 'Consulenza', 'APL'];

    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->boolean('is_reportable')->default(false)->after('is_selectable');
        });

        DB::table('product_categories')
            ->whereIn('name', self::INITIAL_REPORTABLE_CATEGORIES)
            ->update(['is_reportable' => true]);
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('is_reportable');
        });
    }
};
