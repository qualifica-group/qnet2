<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * The Gestione Richieste / Iscritti report column selection the Qualifica
 * catalogue declares (spec 0141 D-6/D-7/AC-008) — the SAME snapshot
 * migration `2026_09_18_110000_add_report_columns_to_product_categories_
 * table` freezes from the retired `config('request-management-report.
 * category_columns')` map, applied here too so an installation seeded AFTER
 * that migration ran (a fresh install) still gets the identical report.
 *
 * Sibling of CategoryInheritanceRules: names are bound by identity to
 * QualificaCatalogSeeder::CATALOG, so a rename there breaks loudly here
 * instead of silently leaving a category unconfigured.
 *
 * ONLY where `report_columns` is still null — unlike is_reportable
 * (QualificaCatalogSeeder::REPORTABLE_CATEGORIES, creation-only), this is
 * NOT: an installation seeded before this map existed must still get it
 * backfilled on the next `db:seed` run. Idempotent either way: a category
 * already configured (an admin's own edit, or a previous run of apply()) is
 * left untouched.
 */
final class ReportColumnsCatalogue
{
    /**
     * @var array<string, list<string>>
     */
    private const array COLUMNS = [
        'GOL' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'Autoimpiego' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'Yisu' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'DIL' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_gestione', 'aule_partenza', 'associati'],
        'Autofinanziato' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aule_partenza', 'associati'],
        'Consulenza' => ['telefonate', 'richiami', 'nuovi_contatti', 'potenziali', 'aziende_inserite', 'presa_appuntamenti', 'trattative_concluse'],
        'APL' => ['telefonate', 'richiami', 'nuovi_contatti', 'invio_presa_in_carico'],
    ];

    public function apply(): void
    {
        foreach (self::COLUMNS as $categoryName => $columns) {
            ProductCategory::query()->where('name', $categoryName)->whereNull('report_columns')->get()
                ->each(fn (ProductCategory $category) => $category->update(['report_columns' => $columns]));
        }
    }
}
