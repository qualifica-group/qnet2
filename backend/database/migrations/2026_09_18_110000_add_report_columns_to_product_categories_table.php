<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `product_categories.report_columns` (spec 0141): the node's OWN selection
 * of Gestione Richieste / Iscritti report indicator columns — nullable JSON
 * array of `config('request-management-report.indicator_columns')` keys,
 * null = inherit the nearest ancestor's (App\Services\ProductCategories\
 * ReportColumnsInheritance, structural walk, reportable or not — same shape
 * as `is_reportable`). Supersedes the hardcoded
 * `config('request-management-report.category_columns')` map keyed by
 * category NAME (spec 0131 D-4-bis, retired by spec 0141).
 *
 * D-6: the backfill below is a FROZEN SNAPSHOT of that map as it stood right
 * before this migration — not a read of the (now deleted) config key — so an
 * installation already seeded keeps its report identical at deploy. Matched
 * on the category's name, case-insensitive and trimmed, same convention the
 * retired config used.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const array SNAPSHOT = [
        'gol' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'autoimpiego' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'yisu' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'dil' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'autofinanziato' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_partenza', 'associati'],
        'consulenza' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aziende_inserite', 'presa_appuntamenti', 'trattative_concluse'],
        'apl' => ['telefonate', 'richiami', 'nuovi_contatti', 'invio_presa_in_carico'],
    ];

    public function up(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->json('report_columns')->nullable()->after('is_reportable');
        });

        foreach (DB::table('product_categories')->select('id', 'name')->get() as $category) {
            $columns = self::SNAPSHOT[mb_strtolower(trim($category->name))] ?? null;

            if ($columns !== null) {
                DB::table('product_categories')->where('id', $category->id)->update([
                    'report_columns' => json_encode(array_values($columns)),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_categories', function (Blueprint $table) {
            $table->dropColumn('report_columns');
        });
    }
};
