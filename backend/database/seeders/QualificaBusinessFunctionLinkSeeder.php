<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Seeder;

/**
 * Links the catalogue categories that have a counterpart among the imported
 * business functions to it (spec 0023):
 *
 *   - "Formazione", the ROOT: a category's EFFECTIVE business function is
 *     resolved own-or-inherited (CategoryHierarchy::effectiveBusinessFunction),
 *     so one row there reaches the whole branch, the GOL regions included;
 *   - "APL", a SUBCATEGORY of "Consulenza" (user directive 2026-09-07): it
 *     hosts its own offer and its own function, so the row sits on the node
 *     itself. Own-or-inherited means its own wins over anything the Consulenza
 *     root may carry, and it reaches nothing else — the sibling subcategories
 *     keep resolving the root's;
 *   - "Presa Appuntamenti", the Consulenza leaf (user directive 2026-09-28):
 *     linked to "Consulenza", the one function this seeder CREATES itself
 *     (OWN_FUNCTIONS) instead of expecting it from the import.
 *
 * The category is resolved by NAME at any depth: `name` is the catalogue's
 * natural key (QualificaCatalogSeeder seeds every node with `firstOrCreate` on
 * it), and only one of the links is a root.
 *
 * Runs LAST, after QualificaLegacyImportSeeder, because the business functions
 * are not part of the static catalogue: they are imported from the external
 * qnet CRM ('business-functions' is the first source of that import). Which is
 * why it is a step of BOTH entry points — QualificaCatalogSeeder chains it
 * after its own optional import offer, and QualificaProductionDataSeeder calls
 * it after the import it runs itself. Running twice is harmless: the second
 * pass finds the links already in place and does nothing.
 *
 * NEVER fatal (user requirement 2026-07-28): with no external system
 * configured — or with a legacy catalogue that simply has no function of that
 * name — the category is left unassigned and the seed carries on. Each link is
 * independent: a missing "APL" function never holds back the "Formazione" one.
 * Nor does it ever STEAL an occupied slot: a category already pointing at some
 * other function keeps it, so an assignment made by hand survives the re-run,
 * in line with the rest of QualificaCatalogSeeder. The same holds for a slot
 * its branch already fills: an ancestor's function is inherited, and a second
 * one on the chain would break the spec 0023 one-per-chain invariant.
 */
class QualificaBusinessFunctionLinkSeeder extends Seeder
{
    /**
     * Catalogue category name => the business function to assign it to. Same
     * name on both sides, but they are two distinct catalogues: the category is
     * seeded by QualificaCatalogSeeder::CATALOG, the function comes from the
     * legacy import.
     *
     * @var array<string, string>
     */
    private const array LINKS = [
        'Formazione' => 'Formazione',
        'APL' => 'APL',
        'Presa Appuntamenti' => 'Consulenza',
    ];

    /**
     * Business functions the legacy system does not supply, created here when
     * no function of that name exists yet (user directive 2026-09-28). `name`
     * is the natural key, as for the imported ones.
     *
     * @var list<string>
     */
    private const array OWN_FUNCTIONS = ['Consulenza'];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function run(): void
    {
        // Step 1: the functions this seeder owns, before any link needs them.
        foreach (self::OWN_FUNCTIONS as $functionName) {
            $this->ensureFunction($functionName);
        }

        // Step 2: the category links, own and imported functions alike.
        foreach (self::LINKS as $categoryName => $functionName) {
            $this->link($categoryName, $functionName);
        }
    }

    private function ensureFunction(string $functionName): void
    {
        if (BusinessFunction::query()->where('name', $functionName)->exists()) {
            return;
        }

        // A per-model create, so the activity log records it like any other.
        BusinessFunction::query()->create(['name' => $functionName]);

        $this->command?->info(sprintf('Business function "%s" created.', $functionName));
    }

    private function link(string $categoryName, string $functionName): void
    {
        // Step 1: the category to assign. Absent only when this runs before the
        // catalogue, which is not a supported order.
        $category = ProductCategory::query()->where('name', $categoryName)->first();

        if ($category === null) {
            return;
        }

        // Step 2: an occupied slot is left alone — only a free one is filled,
        // and one the branch already fills from an ancestor is not free.
        if ($category->business_function_id !== null
            || $this->hierarchy->inheritedBusinessFunctionFor($category->parent_id) !== null) {
            return;
        }

        // Step 3: the imported counterpart. `name` is not unique on business
        // functions, so the lowest id wins rather than an arbitrary row.
        $function = BusinessFunction::query()
            ->where('name', $functionName)
            ->orderBy('id')
            ->first();

        if ($function === null) {
            $this->command?->warn(sprintf(
                'Category "%s" left unassigned: no "%s" business function found.',
                $categoryName,
                $functionName,
            ));

            return;
        }

        // A per-model update, like every other write on this model, so the
        // activity log records the assignment.
        $category->update(['business_function_id' => $function->id]);

        $this->command?->info(sprintf(
            'Category "%s" assigned to the "%s" business function.',
            $categoryName,
            $functionName,
        ));
    }
}
