<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * The Gestione Richieste / Iscritti report column selection the Qualifica
 * catalogue declares for a fresh install: the spec 0141 D-7 snapshot with
 * richiami/nuovi_contatti/potenziali REPLACED by their range-free twins
 * (spec 0159 D-7) — the range-bound twins stay in the catalogue, unselected,
 * for the admin to switch on from the category form. Migration
 * `2026_09_18_110000_add_report_columns_to_product_categories_table` keeps
 * its own frozen copy of the original snapshot.
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
 * left untouched (spec 0159 D-8).
 */
final class ReportColumnsCatalogue
{
    /**
     * @var array<string, list<string>>
     */
    private const array COLUMNS = [
        'GOL' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aule_gestione', 'aule_partenza', 'associati'],
        'Autoimpiego' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aule_gestione', 'aule_partenza', 'associati'],
        'Yisu' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aule_gestione', 'aule_partenza', 'associati'],
        'DIL' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aule_gestione', 'aule_partenza', 'associati'],
        'Autofinanziato' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aule_partenza', 'associati'],
        'Consulenza' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials', 'aziende_inserite', 'presa_appuntamenti', 'trattative_concluse'],
        'APL' => ['telefonate', 'unhandled_callbacks', 'unhandled_new_contacts', 'invio_presa_in_carico'],
    ];

    public function apply(): void
    {
        foreach (self::COLUMNS as $categoryName => $columns) {
            ProductCategory::query()->where('name', $categoryName)->whereNull('report_columns')->get()
                ->each(fn (ProductCategory $category) => $category->update(['report_columns' => $columns]));
        }
    }
}
