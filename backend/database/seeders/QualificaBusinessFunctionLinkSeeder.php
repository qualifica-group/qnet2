<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

/**
 * Links the catalogue's "Formazione" root category to the business function of
 * the same name (spec 0023). Assigned on the ROOT on purpose: a category's
 * EFFECTIVE business function is resolved own-or-inherited
 * (CategoryHierarchy::effectiveBusinessFunction), so one row reaches the whole
 * Formazione branch — the GOL regions included.
 *
 * Runs LAST, after QualificaLegacyImportSeeder, because the business functions
 * are not part of the static catalogue: they are imported from the external
 * qnet CRM ('business-functions' is the first source of that import). Which is
 * why it is a step of BOTH entry points — QualificaCatalogSeeder chains it
 * after its own optional import offer, and QualificaProductionDataSeeder calls
 * it after the import it runs itself. Running twice is harmless: the second
 * pass finds the link already in place and does nothing.
 *
 * NEVER fatal (user requirement 2026-07-28): with no external system
 * configured — or with a legacy catalogue that simply has no "Formazione"
 * function — the category is left unassigned and the seed carries on. Nor does
 * it ever STEAL an occupied slot: a category already pointing at some other
 * function keeps it, so an assignment made by hand survives the re-run, in line
 * with the rest of QualificaCatalogSeeder.
 */
class QualificaBusinessFunctionLinkSeeder extends Seeder
{
    /**
     * The root category to assign, and the business function to assign it to.
     * Same name on both sides, but they are two distinct catalogues: the
     * category is seeded by QualificaCatalogSeeder::CATALOG, the function comes
     * from the legacy import.
     */
    private const string CATEGORY = 'Formazione';

    private const string BUSINESS_FUNCTION = 'Formazione';

    public function run(): void
    {
        // Step 1: the root of the branch to assign. Absent only when this runs
        // before the catalogue, which is not a supported order.
        $category = ProductCategory::query()
            ->whereNull('parent_id')
            ->where('name', self::CATEGORY)
            ->first();

        if ($category === null) {
            return;
        }

        // Step 2: an occupied slot is left alone — only a free one is filled.
        if ($category->business_function_id !== null) {
            return;
        }

        // Step 3: the imported counterpart. `name` is not unique on business
        // functions, so the lowest id wins rather than an arbitrary row.
        $function = BusinessFunction::query()
            ->where('name', self::BUSINESS_FUNCTION)
            ->orderBy('id')
            ->first();

        if ($function === null) {
            $this->command?->warn(sprintf(
                'Category "%s" left unassigned: no "%s" business function found.',
                self::CATEGORY,
                self::BUSINESS_FUNCTION,
            ));

            return;
        }

        // A per-model update, like every other write on this model, so the
        // activity log records the assignment.
        $category->update(['business_function_id' => $function->id]);

        $this->command?->info(sprintf(
            'Category "%s" assigned to the "%s" business function.',
            self::CATEGORY,
            self::BUSINESS_FUNCTION,
        ));
    }
}
