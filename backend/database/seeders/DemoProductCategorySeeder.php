<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Illuminate\Database\Seeder;

/**
 * The demo product-category tree (spec 0017) with its attributes in BOTH usage
 * contexts (spec 0061) and the form sections that group them (spec 0062) —
 * the demo counterpart of the client's QualificaCatalogSeeder chain, built on
 * the same shared concerns so the two never drift apart.
 *
 * It closes a real hole in the demo dataset: categories are what
 * `product_lines` pairs with a business function and what scopes
 * `products_of_interest`, and BOTH are mandatory on the opportunity form —
 * with no category seeded, DemoOpportunitySeeder could only produce rows that
 * the form itself would refuse to submit.
 *
 * ONE LAYOUT ROW PER CATEGORY, never one on the root: a layout is not
 * inherited (AttributeLayoutService reads the exact category, no ancestor
 * walk), while an attribute ASSIGNMENT is — hence the root-level assignments
 * and the per-category layouts built from each category's own effective set.
 *
 * Idempotent AND non-destructive: categories/attributes keyed on their natural
 * key, assignments and options additive, a category whose layout is already
 * configured (previous run, or by hand from the configurator) left untouched.
 *
 * Depends on DemoBusinessFunctionSeeder for the branch's business function —
 * absent, the tree is still seeded and only the product-line pairing is lost.
 */
class DemoProductCategorySeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        // Step 1: the tree, roots carrying the branch's business function.
        $this->seedTree();

        // Step 2: the attributes, each on the category the catalogue scopes it
        // to — after the WHOLE tree, since an assignment may target any node.
        $this->seedAttributes(DemoCategoryCatalogue::PRODUCT_ATTRIBUTES, AttributeContext::Product);
        $this->seedAttributes(DemoCategoryCatalogue::OPPORTUNITY_ATTRIBUTES, AttributeContext::Opportunity);

        // Step 3: the form sections, one row per category per context.
        $this->seedLayouts(DemoCategoryCatalogue::PRODUCT_SECTIONS, AttributeContext::Product);
        $this->seedLayouts(DemoCategoryCatalogue::OPPORTUNITY_SECTIONS, AttributeContext::Opportunity);
    }

    private function seedTree(): void
    {
        foreach (DemoCategoryCatalogue::TREE as $rootName => $branch) {
            $businessFunctionId = BusinessFunction::query()
                ->where('name', $branch['business_function'])
                ->value('id');

            $root = ProductCategory::firstOrCreate(
                ['name' => $rootName],
                ['parent_id' => null, 'business_function_id' => $businessFunctionId],
            );

            // A tree seeded before the business functions were (partial run)
            // keeps its unpaired categories forever otherwise. A function set
            // by hand is never overwritten.
            if ($root->business_function_id === null && $businessFunctionId !== null) {
                $root->update(['business_function_id' => $businessFunctionId]);
            }

            foreach ($branch['children'] as $childName) {
                ProductCategory::firstOrCreate(['name' => $childName], ['parent_id' => $root->id]);
            }
        }
    }

    /**
     * @param  array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>>  $specsByCategory
     */
    private function seedAttributes(array $specsByCategory, AttributeContext $context): void
    {
        foreach ($specsByCategory as $categoryName => $specs) {
            // Created by seedTree() above: a miss means the two lists drifted
            // apart, which must fail loudly rather than silently drop a whole
            // category's fields.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            $this->seedCategoryAttributes($category, $specs, $context);
        }
    }

    /**
     * @param  array<string, list<array{id: string, title: string, rows: list<list<string>>}>>  $sectionsByBranch
     */
    private function seedLayouts(array $sectionsByBranch, AttributeContext $context): void
    {
        foreach (DemoCategoryCatalogue::categoryNames() as $categoryName) {
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
            $branchSections = $sectionsByBranch[DemoCategoryCatalogue::branchOf($categoryName)] ?? [];

            $this->seedLayout($category, $context, $branchSections);
        }
    }

    /**
     * @param  list<array{id: string, title: string, rows: list<list<string>>}>  $branchSections
     */
    private function seedLayout(ProductCategory $category, AttributeContext $context, array $branchSections): void
    {
        // A configured layout is user data: leave it exactly as it is.
        if ($branchSections === [] || $this->layouts->resolveExact($category, $context, LayoutFormScope::All) !== null) {
            return;
        }

        $effectiveCodes = $this->hierarchy
            ->effectiveAttributes($category, $context)
            ->pluck('code')
            ->all();

        $sections = $this->sections($branchSections, $effectiveCodes);

        if ($sections === []) {
            return;
        }

        $this->layouts->upsert($category, $context, LayoutFormScope::All, ['sections' => $sections]);
    }

    /**
     * Each branch section reduced to the codes THIS category actually
     * resolves; a section left with no row is dropped entirely (the online-only
     * "Erogazione" block on an in-person category, for instance).
     *
     * @param  list<array{id: string, title: string, rows: list<list<string>>}>  $branchSections
     * @param  list<string>  $effectiveCodes
     * @return list<array<string, mixed>>
     */
    private function sections(array $branchSections, array $effectiveCodes): array
    {
        $sections = [];

        foreach ($branchSections as $section) {
            $rows = $this->keepAllowedCodes($section['rows'], $effectiveCodes);

            if ($rows === []) {
                continue;
            }

            $sections[] = $this->layoutSection($section['id'], $section['title'], $rows, count($sections));
        }

        return $sections;
    }
}
