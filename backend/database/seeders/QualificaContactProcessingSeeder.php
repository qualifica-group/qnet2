<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\Concerns\RetiresAttributes;
use Database\Seeders\Concerns\SeedsAttributeLayouts;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\ContactProcessingAttributeCatalogue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * The client's "Dati Lavorazione Contatto" set (spec 0061): what the operator
 * records while working a request, assigned to the categories that use it —
 * the Formazione root (inherited by its whole branch), the "GOL" container and
 * three of its regions, and the "Autofinanziato" subcategory. The catalogue
 * rows live in
 * QualificaCatalog\ContactProcessingAttributeCatalogue; this seeder assigns
 * them and groups them into one form section (spec 0062).
 *
 * TWO CONTEXTS, ONE CATALOGUE (self::CONTEXTS): the same codes, on the same
 * categories, are provisioned for the Offerta (`quote`, spec 0084) AND for the
 * Commessa (`work_order`, spec 0098 D-2). The two are independent — both
 * `attribute_category` and `attribute_layouts` are keyed on the context — so
 * each context gets its OWN pivot row and its OWN layout row rather than
 * sharing one: withdrawing a field from the Commessa afterwards, from the
 * category configurator, leaves the Offerta untouched.
 *
 * IT OWNS THE COMMESSA LAYOUT ONLY. On the Offerta side this set shares the
 * form with two more catalogues ("Dati corso" and "Dati Aula", moved off the
 * product by the user directive 2026-09-08) and a category holds ONE layout
 * row per context, so composing that row is QualificaQuoteLayoutSeeder's job —
 * whichever seeder wrote it first would otherwise leave the others' fields to
 * the renderer's synthesized, collapsed section. Nothing else contributes to
 * the Commessa form, which is why this one stays here.
 *
 * ONE LAYOUT ROW PER CATEGORY, not one on the root: a layout is not inherited
 * — AttributeLayoutMerger reads the layout of each category CONTRIBUTING to
 * the record (its product lines' categories), never an ancestor's. A single
 * root row would render nowhere. Each category's section is built from its OWN
 * effective attributes, so "Autofinanziato" carries the training set AND its
 * own two fields.
 *
 * Idempotent AND non-destructive: attributes keyed on `code` (an imported
 * q-crm row is adopted, never duplicated), assignments and options additive, a
 * category whose layout for that context is already configured left
 * untouched.
 *
 * The ONE subtractive step is the retirement of the codes the catalogue no
 * longer declares (ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES):
 * without it, dropping a row from the catalogue would be a no-op on every
 * installation already seeded by an earlier revision.
 */
class QualificaContactProcessingSeeder extends Seeder
{
    use RetiresAttributes;
    use SeedsAttributeLayouts;
    use SeedsCategoryAttributes;

    private const string SECTION_ID = 'contact-processing';

    /**
     * The contexts the ATTRIBUTES are assigned in, in order. Adding one here
     * is all it takes: the assignment step iterates it instead of naming a
     * context.
     *
     * @var list<AttributeContext>
     */
    private const array CONTEXTS = [AttributeContext::Quote, AttributeContext::WorkOrder];

    /**
     * The context whose LAYOUT this seeder writes — the Commessa only. The
     * Offerta form is composed by QualificaQuoteLayoutSeeder, which places
     * this catalogue's section alongside "Dati corso" and "Dati Aula".
     */
    private const AttributeContext LAYOUT_CONTEXT = AttributeContext::WorkOrder;

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

        // Step 2: the attributes, each on the category the client scoped it to,
        // in both contexts.
        //
        // Spec 0084 moved the "Informazioni aggiuntive" from the Opportunity to
        // the Offerta, so these go in `AttributeContext::Quote`. They used to be
        // seeded in `::Opportunity`, which NOTHING renders any more: leaving
        // them there would have configured a real client catalogue into a
        // context no screen reads — present in the database, invisible to the
        // operator, and impossible to tell apart from a configuration mistake.
        foreach (ContactProcessingAttributeCatalogue::ATTRIBUTES as $categoryName => $specs) {
            // Created by QualificaCatalogSeeder: a miss means the two lists
            // drifted apart, which must fail loudly rather than silently drop
            // a whole category's fields.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            foreach (self::CONTEXTS as $context) {
                $this->seedCategoryAttributes($category, $specs, $context);
            }
        }

        // Step 2-bis: the codes the catalogue stopped declaring, withdrawn from
        // the categories an earlier revision put them on.
        $this->retireAttributes();

        // Step 3: the Commessa section, on every category that can contribute
        // to a record. AFTER step 2, never interleaved with it:
        // AttributeLayoutService validates each code against the category's
        // effective set in that context, so the assignments have to be there
        // first.
        foreach ($this->layoutCategories() as $category) {
            $this->seedLayout($category);
        }
    }

    /**
     * Withdraws every RETIRED_ATTRIBUTES code from every context: the
     * directive is "in every category", not "in this context". A no-op on a
     * clean database, where the code was never created.
     */
    private function retireAttributes(): void
    {
        $this->retireAttributeCodes(ContactProcessingAttributeCatalogue::RETIRED_ATTRIBUTES);
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
     * branch, the training fields reaching it all by inheritance.
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

        // The Formazione branch alone: the two Consulenza leaves carry no
        // attribute since the 2026-09-10 directive, so composing a layout for
        // them would prune to nothing anyway.
        $categories = ProductCategory::query()->whereIn('id', $branchIds)->get();

        // Root-first: seedLayout() asks what each category INHERITS, which is
        // only settled once every level above it has been visited.
        return $this->sortRootFirst($categories, $this->hierarchy->parentIdMap());
    }

    private function seedLayout(ProductCategory $category): void
    {
        $existing = $this->layouts->resolveExact($category, self::LAYOUT_CONTEXT, LayoutFormScope::All);

        $effective = $this->hierarchy
            ->effectiveAttributes($category, self::LAYOUT_CONTEXT)
            ->pluck('code')
            ->all();

        $sections = $this->compose(ContactProcessingAttributeCatalogue::ROWS, $effective);

        // A configured layout is user data: leave it exactly as it is, unless
        // it is verbatim a composition THIS seeder wrote — today's, which then
        // may be a redundant copy of the ancestor's (spec 0115), or a previous
        // revision's, which has to be recomposed or the installation would
        // stay frozen on rows that predate the current catalogue.
        if ($existing !== null && ! $this->isOwnComposition($existing, $effective, $sections)) {
            return;
        }

        if ($this->inheritedRendersSame($this->layouts, $category, self::LAYOUT_CONTEXT, $sections)) {
            if ($existing !== null) {
                $this->layouts->upsert($category, self::LAYOUT_CONTEXT, LayoutFormScope::All, null);
            }

            return;
        }

        if ($sections === []) {
            return;
        }

        $this->layouts->upsert($category, self::LAYOUT_CONTEXT, LayoutFormScope::All, ['sections' => $sections]);
    }

    /**
     * Whether $blob is one this seeder wrote for THIS category — today's
     * composition or the one the previous revision of the catalogue's rows
     * produced. Anything else is a human's work and stays untouched.
     *
     * @param  array<string, mixed>  $blob
     * @param  list<string>  $effective
     * @param  list<array<string, mixed>>  $current
     */
    private function isOwnComposition(array $blob, array $effective, array $current): bool
    {
        $candidates = [$current, $this->compose(ContactProcessingAttributeCatalogue::PREVIOUS_ROWS, $effective)];

        foreach ($candidates as $sections) {
            if ($blob == ['sections' => $sections]) {
                return true;
            }
        }

        return false;
    }

    /**
     * This catalogue's single section, pruned to what $effective resolves —
     * empty when the category resolves none of its codes.
     *
     * @param  list<list<string>>  $rows
     * @param  list<string>  $effective
     * @return list<array<string, mixed>>
     */
    private function compose(array $rows, array $effective): array
    {
        $kept = $this->keepAllowedCodes($rows, $effective);

        return $kept === []
            ? []
            : [$this->layoutSection(self::SECTION_ID, ContactProcessingAttributeCatalogue::SECTION_TITLE, $kept, 0)];
    }
}
