<?php

namespace Database\Seeders;

use App\Migrations\Sources\ProductsSource;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Provides the business functions of the catalogue and links its categories
 * to them (spec 0023):
 *
 *   - "Formazione", the ROOT: a category's EFFECTIVE business function is
 *     resolved own-or-inherited (CategoryHierarchy::effectiveBusinessFunction),
 *     so one row there reaches the whole branch, the GOL regions included;
 *   - "APL", the root of its own branch (user directive 2026-09-07);
 *   - "Presa Appuntamenti", the Consulenza leaf (user directive 2026-09-28),
 *     linked to "Consulenza";
 *   - "No categoria", the holding root of the legacy products imported without
 *     a category (user decision 2026-10-12): linked to "No funzione aziendale",
 *     so an offer line on one of those products can still extend its
 *     opportunity's coverage, which requires a function.
 *
 * Every one of those functions is CREATED here when no function of that name
 * exists yet, compared without case (user directive 2026-10-14): the catalogue
 * no longer waits for the legacy import to have its "Formazione" and "APL"
 * functions. The import ADOPTS them by name (BusinessFunctionsSource) instead
 * of adding a duplicate, so it can run before or after this seeder.
 *
 * The category is resolved by NAME at any depth: `name` is the catalogue's
 * natural key (QualificaCatalogSeeder seeds every node with `firstOrCreate` on
 * it). It never STEALS an occupied slot: a category already pointing at some
 * other function keeps it, so an assignment made by hand survives the re-run,
 * in line with the rest of QualificaCatalogSeeder. The same holds for a slot
 * its branch already fills: an ancestor's function is inherited, and a second
 * one on the chain would break the spec 0023 one-per-chain invariant.
 */
class QualificaBusinessFunctionLinkSeeder extends Seeder
{
    /**
     * Catalogue category name => the business function to assign it to. The
     * category is seeded by QualificaCatalogSeeder::CATALOG, the function by
     * this seeder.
     *
     * @var array<string, string>
     */
    private const array LINKS = [
        'Formazione' => 'Formazione',
        'APL' => 'APL',
        'Presa Appuntamenti' => 'Consulenza',
        ProductsSource::UNCATEGORIZED_CATEGORY => self::UNCATEGORIZED_FUNCTION,
    ];

    /**
     * The function of the "No categoria" holding root (user decision
     * 2026-10-12): without one, every legacy offer carrying one of its
     * products failed the import.
     */
    private const string UNCATEGORIZED_FUNCTION = 'No funzione aziendale';

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function run(): void
    {
        foreach (self::LINKS as $categoryName => $functionName) {
            // Step 1: the function, before the link needs it.
            $function = $this->ensureFunction($functionName);

            // Step 2: the category link.
            $this->link($categoryName, $function);
        }
    }

    private function ensureFunction(string $functionName): BusinessFunction
    {
        $function = $this->functionNamed($functionName);

        if ($function !== null) {
            return $function;
        }

        $this->command?->info(sprintf('Business function "%s" created.', $functionName));

        // A per-model create, so the activity log records it like any other.
        return BusinessFunction::query()->create(['name' => $functionName]);
    }

    private function link(string $categoryName, BusinessFunction $function): void
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

        // A per-model update, like every other write on this model, so the
        // activity log records the assignment.
        $category->update(['business_function_id' => $function->id]);

        $this->command?->info(sprintf(
            'Category "%s" assigned to the "%s" business function.',
            $categoryName,
            $function->name,
        ));
    }

    /**
     * `name` is not unique on business functions, so the lowest id wins rather
     * than an arbitrary row; compared without case, as the import adopts it.
     */
    private function functionNamed(string $functionName): ?BusinessFunction
    {
        return BusinessFunction::query()
            ->whereRaw('LOWER(name) = ?', [Str::lower($functionName)])
            ->orderBy('id')
            ->first();
    }
}
