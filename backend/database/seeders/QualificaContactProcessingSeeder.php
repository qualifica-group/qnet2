<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * The client's "Dati Lavorazione Contatto" set (spec 0061, OPPORTUNITY
 * context): what the operator records while working a request, assigned to the
 * categories that use it — the Formazione root (inherited by its whole
 * branch), the "GOL" container and three of its regions, the "Autofinanziato"
 * subcategory, and the two Consulenza leaves. The catalogue rows live in
 * QualificaCatalog\ContactProcessingAttributeCatalogue; this seeder assigns
 * them and groups them into one form section (spec 0062).
 *
 * ONE LAYOUT ROW PER CATEGORY, not one on the root: like the Product side, a
 * layout is not inherited — AttributeLayoutMerger reads the
 * layout of each category CONTRIBUTING to the request (its product lines'
 * categories), never an ancestor's. A single root row would render nowhere.
 * Each category's section is built from its OWN effective attributes, so
 * "Autofinanziato" carries the training set AND its own two fields, while a
 * Consulenza leaf carries only the company-appointment ones.
 *
 * Idempotent AND non-destructive: attributes keyed on `code` (an imported
 * q-crm row is adopted, never duplicated), assignments and options additive, a
 * category whose opportunity layout is already configured left untouched.
 *
 * The ONE subtractive step is the retirement of the codes the catalogue no
 * longer declares (ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES):
 * without it, dropping a row from the catalogue would be a no-op on every
 * installation already seeded by an earlier revision.
 */
class QualificaContactProcessingSeeder extends Seeder
{
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    private const string SECTION_ID = 'contact-processing';

    public function __construct(
        private readonly AttributeLayoutService $layouts,
        private readonly CategoryHierarchy $hierarchy,
    ) {}

    public function run(): void
    {
        // Step 1: the legacy free-text pick list, BEFORE the catalogue below
        // creates its options — a no-op on a clean database, where the same
        // attribute is created as an enum outright.
        $this->promoteDegree();

        // Step 2: the attributes, each on the category the client scoped it to.
        foreach (ContactProcessingAttributeCatalogue::ATTRIBUTES as $categoryName => $specs) {
            // Created by QualificaCatalogSeeder: a miss means the two lists
            // drifted apart, which must fail loudly rather than silently drop
            // a whole category's fields.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            $this->seedCategoryAttributes($category, $specs, AttributeContext::Opportunity);
        }

        // Step 2-bis: the codes the catalogue stopped declaring, withdrawn from
        // the categories an earlier revision put them on.
        $this->retireAttributes();

        // Step 3: the section, on every category that can contribute to a
        // request.
        foreach ($this->layoutCategories() as $category) {
            $this->seedLayout($category);
        }
    }

    /**
     * Withdraws every RETIRED_ATTRIBUTES code, in two passes because the two
     * places that can still surface the field are independent:
     *   - the assignments, so no category resolves the code any more and the
     *     work panel stops rendering it (context-agnostic on purpose: the
     *     directive is "in every category", not "in this context");
     *   - the persisted layout blobs, because a stale item is not merely
     *     invisible — AttributeLayoutMerger does drop it at
     *     render time, but AttributeLayoutValidator rejects a code outside the
     *     category's effective set on WRITE, so leaving it there would 422 the
     *     next save from the layout configurator.
     *
     * A no-op on a clean database, where the code was never created.
     */
    private function retireAttributes(): void
    {
        $retired = Attribute::query()
            ->whereIn('code', ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES)
            ->get();

        if ($retired->isEmpty()) {
            return;
        }

        foreach ($retired as $attribute) {
            $attribute->categories()->detach();
        }

        $this->stripFromLayouts($retired->pluck('code')->all());
    }

    /**
     * Rewrites the blob of every layout still placing one of $codes, pruning
     * the rows and sections left empty. Written straight onto the model rather
     * than through AttributeLayoutService::upsert(): that path validates the
     * WHOLE blob against the category's effective set, which is exactly what a
     * hand-configured layout may legitimately fail on for an unrelated reason —
     * and a retirement must never take a user's layout down with it.
     *
     * @param  list<string>  $codes
     */
    private function stripFromLayouts(array $codes): void
    {
        AttributeLayout::query()->each(function (AttributeLayout $row) use ($codes): void {
            $sections = $this->withoutCodes($row->layout['sections'] ?? [], $codes);

            if ($sections === ($row->layout['sections'] ?? [])) {
                return;
            }

            // An emptied layout means "back to flat" (AttributeLayoutService),
            // which is a deleted row, not a blob with zero sections.
            $sections === []
                ? $row->delete()
                : $row->update(['layout' => ['sections' => $sections]]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  list<string>  $codes
     * @return array<int, array<string, mixed>>
     */
    private function withoutCodes(array $sections, array $codes): array
    {
        $pruned = array_map(static function (array $section) use ($codes): array {
            $rows = array_map(static function (array $row) use ($codes): array {
                $row['items'] = array_values(array_filter(
                    $row['items'] ?? [],
                    static fn (array $item): bool => ! in_array($item['attribute_code'] ?? null, $codes, true),
                ));

                return $row;
            }, $section['rows'] ?? []);

            $section['rows'] = array_values(array_filter($rows, static fn (array $row): bool => $row['items'] !== []));

            return $section;
        }, $sections);

        return array_values(array_filter($pruned, static fn (array $section): bool => $section['rows'] !== []));
    }

    /**
     * The q-crm import created "Titolo di Studio" as free text; the client's
     * list wants a pick list. Promoting the type rewrites how every stored
     * value is read.
     *
     * Spec 0084: this used to guard the promotion on NO Opportunity already
     * carrying a value (`opportunities.attribute_values`, dropped without
     * migration by D-2) — that value store is gone, so there is nothing left
     * for a promotion to silently reinterpret, and the guard is removed
     * along with it.
     */
    private function promoteDegree(): void
    {
        $degree = Attribute::query()
            ->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)
            ->first();

        if ($degree === null || $degree->type === 'enum') {
            return;
        }

        $degree->update(['type' => 'enum']);
    }

    /**
     * Every category a request can be filed under for this set: the Formazione
     * branch (the training fields reach it all by inheritance) plus the two
     * Consulenza leaves, which are siblings and carry their own set.
     *
     * @return Collection<int, ProductCategory>
     */
    private function layoutCategories(): Collection
    {
        $root = ProductCategory::query()
            ->where('name', ContactProcessingAttributeCatalogue::TRAINING_CATEGORY)
            ->whereNull('parent_id')
            ->firstOrFail();

        $branchIds = [$root->id, ...$this->hierarchy->descendantIds($root->id)];

        return ProductCategory::query()
            ->whereIn('id', $branchIds)
            ->orWhereIn('name', ContactProcessingAttributeCatalogue::CONSULTING_CATEGORIES)
            ->get();
    }

    private function seedLayout(ProductCategory $category): void
    {
        // A configured layout is user data: leave it exactly as it is.
        if ($this->layouts->resolveExact($category, AttributeContext::Opportunity, LayoutFormScope::All) !== null) {
            return;
        }

        $effective = $this->hierarchy
            ->effectiveAttributes($category, AttributeContext::Opportunity)
            ->pluck('code')
            ->all();

        $rows = $this->keepAllowedCodes(ContactProcessingAttributeCatalogue::ROWS, $effective);

        if ($rows === []) {
            return;
        }

        $this->layouts->upsert($category, AttributeContext::Opportunity, LayoutFormScope::All, [
            'sections' => [
                $this->layoutSection(
                    self::SECTION_ID,
                    ContactProcessingAttributeCatalogue::SECTION_TITLE,
                    $rows,
                    0,
                ),
            ],
        ]);
    }
}
