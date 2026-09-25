<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * The Gestione Richieste / Iscritti report column selection the Qualifica
 * catalogue declares for a fresh install (spec 0159 D-7, superseding the
 * spec 0141 D-7 snapshot here; the migration
 * `2026_09_18_110000_add_report_columns_to_product_categories_table` keeps
 * its own frozen copy of the old one). Every category gets the range-free
 * counterpart of the columns it used to have, plus its constant stubs; the
 * range-bound columns stay in the catalogue, unselected, for the admin to
 * switch on from the category form.
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
        'GOL' => ['aule_gestione', 'aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'Autoimpiego' => ['aule_gestione', 'aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'Yisu' => ['aule_gestione', 'aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'DIL' => ['aule_gestione', 'aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'Autofinanziato' => ['aule_partenza', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'Consulenza' => ['presa_appuntamenti', 'unhandled_callbacks', 'unhandled_new_contacts', 'current_potentials'],
        'APL' => ['unhandled_callbacks', 'unhandled_new_contacts'],
    ];

    public function apply(): void
    {
        foreach (self::COLUMNS as $categoryName => $columns) {
            ProductCategory::query()->where('name', $categoryName)->whereNull('report_columns')->get()
                ->each(fn (ProductCategory $category) => $category->update(['report_columns' => $columns]));
        }
    }
}
