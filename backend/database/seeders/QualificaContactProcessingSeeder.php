<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\Opportunity;
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
 * branch), the "Autofinanziato" subcategory, and the two Consulenza leaves.
 * The catalogue rows live in
 * QualificaCatalog\ContactProcessingAttributeCatalogue; this seeder assigns
 * them and groups them into one form section (spec 0062).
 *
 * ONE LAYOUT ROW PER CATEGORY, not one on the root: like the Product side, a
 * layout is not inherited — OpportunityAttributeLayoutResolver reads the
 * layout of each category CONTRIBUTING to the request (its product lines'
 * categories), never an ancestor's. A single root row would render nowhere.
 * Each category's section is built from its OWN effective attributes, so
 * "Autofinanziato" carries the training set AND its own two fields, while a
 * Consulenza leaf carries only the company-appointment ones.
 *
 * Idempotent AND non-destructive: attributes keyed on `code` (an imported
 * q-crm row is adopted, never duplicated), assignments and options additive, a
 * category whose opportunity layout is already configured left untouched.
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

        // Step 3: the section, on every category that can contribute to a
        // request.
        foreach ($this->layoutCategories() as $category) {
            $this->seedLayout($category);
        }
    }

    /**
     * The q-crm import created "Titolo di Studio" as free text; the client's
     * list wants a pick list. Promoting the type rewrites how every stored
     * value is read, so it only happens while NO request carries one —
     * otherwise the field keeps its imported type and the options are simply
     * ignored by a `text` control, which is recoverable, unlike silently
     * reinterpreting live data.
     */
    private function promoteDegree(): void
    {
        $degree = Attribute::query()
            ->where('code', ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)
            ->first();

        if ($degree === null || $degree->type === 'enum') {
            return;
        }

        $isUsed = Opportunity::query()
            ->whereJsonContainsKey('attribute_values->'.ContactProcessingAttributeCatalogue::DEGREE_ATTRIBUTE)
            ->exists();

        if ($isUsed) {
            $this->command?->warn(sprintf(
                'Attributo "%s" lasciato di tipo %s: alcune richieste hanno gia\' un valore.',
                $degree->name,
                $degree->type,
            ));

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
