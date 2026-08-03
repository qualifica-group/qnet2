<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use App\Services\ProductCategories\CategoryManagementModeInheritance;
use App\Services\ProductService;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
use Database\Seeders\QualificaCatalog\TrainingCourseCatalogue;
use Illuminate\Database\Seeder;

/**
 * The client's hard-coded reference data — the rows that are known up front and
 * are NOT imported from the legacy system:
 *
 *   - the source catalogue (spec 0018): the fixed provenance list used to
 *     classify registry/lead/opportunity records;
 *   - the reward type catalogue (spec 0058): the voucher/reward types in use;
 *   - the reference category catalogue (spec 0017): a two-root category tree
 *     (Formazione / Consulenza) with its subcategories and the regional
 *     declinations under GOL. The first TWO levels — the two roots and every
 *     subcategory under them — are seeded as CONTAINERS (`is_selectable =
 *     false`, spec 0074): they group the tree and hand their attributes down,
 *     while products, opportunity lines, projects, campaigns and commission
 *     rules are classified on the third level, today the `GOL - <Regione>`
 *     rows — plus the one subcategory that hosts its offer directly,
 *     "Autofinanziato" (see SELECTABLE_SUBCATEGORIES). The "Formazione"
 *     branch also carries its product-context attributes (spec 0061) —
 *     "Ore complessive" and the
 *     "Dati Aula" set of QualificaCatalog\ClassroomAttributeCatalogue —
 *     assigned to the root and inherited by every descendant, then grouped
 *     into form sections by QualificaClassroomLayoutSeeder (spec 0062). The
 *     OPPORTUNITY-context counterpart ("Dati Lavorazione Contatto", scoped to
 *     Formazione / Autofinanziato / the two Consulenza leaves) is delegated to
 *     QualificaContactProcessingSeeder;
 *   - the GOL training courses (QualificaCatalog\TrainingCourseCatalogue): one
 *     SERVICE product per funded course, filed under its own region's
 *     `GOL - <Regione>` category and carrying its duration in that attribute.
 *     Cost/price stay 0 — they are edited later through the CRUD modules;
 *   - the self-funded courses (QualificaCatalog\SelfFundedCourseCatalogue):
 *     one SERVICE product per row under the "Autofinanziato" subcategory,
 *     with its list price and its delivery mode ("Modalità di svolgimento",
 *     an enum attribute assigned to that subcategory alone). No other product
 *     is seeded;
 *   - the "stati di lavorazione" (spec 0047), delegated to
 *     QualificaWorkflowSeeder as the last step: one OpportunityWorkflow per
 *     category of QualificaCatalog\WorkflowStatusCatalogue, matched on that
 *     category and carrying its own working-state pick list;
 *   - the "Formazione" root's business function link (spec 0023), delegated to
 *     QualificaBusinessFunctionLinkSeeder as the very last step: that function
 *     is imported from the external qnet CRM, not seeded here, so the link is
 *     a documented no-op whenever the import did not run.
 *
 * Deliberately separate from QualificaTemplateSeeder, which provisions
 * STRUCTURE ONLY (the custom field definitions) and creates no domain row.
 * Both are steps of QualificaProductionDataSeeder, which is the entry point.
 *
 * Idempotent: `firstOrCreate` on the natural name key for sources, reward
 * types, categories and courses — a re-run never duplicates rows nor
 * overwrites manual edits.
 *
 * Runs BEFORE QualificaLegacyImportSeeder: that step adopts the source
 * catalogue below by name instead of duplicating it, and nests its imported
 * taxonomy under the "Consulenza" root created below. Launched on its own,
 * this seeder OFFERS to chain it (see offerLegacyImport) so the pair is not
 * left half-run by hand.
 */
class QualificaCatalogSeeder extends Seeder
{
    use SeedsCategoryAttributes;

    /**
     * The client's fixed source catalogue (spec 0018): user-facing domain
     * values, kept in their original language. Seeded in order.
     *
     * @var list<string>
     */
    private const array SOURCES = [
        'Diretto',
        'Passaparola',
        'Diretto / Passaparola',
        'Social',
        'Sito',
        'Spoki',
        'Centralino',
        'In Sede',
        'Segnalatore',
        'Spontaneo',
    ];

    /**
     * The client's reward type catalogue (spec 0058): name => palette token
     * from `BADGE_COLOR_TOKENS` (the grid badge resolves the value by TOKEN
     * NAME, never an arbitrary hex). Names are user-facing domain values,
     * kept in their original language.
     *
     * @var array<string, string>
     */
    private const array REWARD_TYPES = [
        'Buono Amazon' => 'orange',
    ];

    /**
     * The client's reference category catalogue (spec 0017): root category =>
     * (subcategory => list of leaf children, empty when nothing is classified
     * under that subcategory yet). The tree has no depth limit. Names are
     * user-facing domain values, kept in their original language, and are the
     * natural keys used for idempotent `firstOrCreate` on re-run.
     *
     * The first two levels are CONTAINERS (spec 0074, user directive
     * 2026-08-03), save the exceptions listed in SELECTABLE_SUBCATEGORIES:
     * classification happens on the third level, so an empty child list means
     * "no target seeded under this subcategory yet" — the children added there
     * tomorrow become the selectable ones.
     *
     * @var array<string, array<string, list<string>>>
     */
    private const array CATALOG = [
        'Formazione' => [
            'GOL' => [
                'GOL - Molise',
                'GOL - Abruzzo',
                'GOL - Calabria',
                'GOL - Campania',
                'GOL - Lombardia',
                'GOL - Lazio',
                'GOL - Umbria',
                'GOL - Puglia',
                'GOL - Basilicata',
                'GOL - Sicilia',
            ],
            'Autoimpiego' => [],
            'Yisu' => [],
            'Autofinanziato' => [],
            'DIL' => [],
        ],
        'Consulenza' => [
            'Trattative in Corso' => [],
            'Presa Appuntamenti' => [],
        ],
    ];

    /**
     * The second-level nodes that ARE classification targets, by exception to
     * the container rule above: a subcategory that hosts its own offer instead
     * of grouping children. "Autofinanziato" is one — seedSelfFundedCourses()
     * files every self-funded course directly on it, so a container there would
     * leave those products under a category nothing can be classified on (user
     * directive 2026-08-03). Bound by identity to the catalogue that files
     * them, so a rename breaks loudly instead of silently demoting the node.
     *
     * @var list<string>
     */
    private const array SELECTABLE_SUBCATEGORIES = [
        SelfFundedCourseCatalogue::CATEGORY,
    ];

    /**
     * `management_mode` (spec 0077) per catalogue root: "Formazione" is
     * "single" (user directive 2026-08-03), "Consulenza" stays "multiple"
     * (D-8 default) — listed explicitly so a re-run realigns both.
     */
    private const array CATALOG_MANAGEMENT_MODES = [
        'Formazione' => CategoryManagementMode::Single,
        'Consulenza' => CategoryManagementMode::Multiple,
    ];

    /**
     * Product-context attributes (spec 0061): category name => list of
     * catalogue attribute specs. The assignment is made at the HIGHEST node
     * that needs the field, because `inherits_product_attributes` defaults to
     * true and a category's EFFECTIVE attributes are its own UNION every
     * ancestor's — so "Ore complessive" reaches the whole "Formazione" branch
     * (its subcategories AND the regional GOL children) from a single pivot
     * row, while "Modalità di svolgimento" stays confined to the
     * "Autofinanziato" subtree, the only offer sold with a delivery mode.
     *
     * The "Dati Aula" fields (ClassroomAttributeCatalogue) ride on the same
     * root assignment, for the same reason: they describe the classroom
     * edition of ANY Formazione product, regional or self-funded.
     *
     * `code` is the English identifier (the catalogue's natural key, and its
     * `^[a-z0-9_]+$` format); `name` is the user-facing label, kept in its
     * original language. `type` is a FieldTypeRegistry key; `options` is
     * required by, and only meaningful for, the `enum` type, as is
     * `relation_target` for the `relation` one.
     *
     * @var array<string, list<array{code: string, name: string, type: string, options?: list<array{value: string, label: string}>, relation_target?: array<string, mixed>}>>
     */
    private const array CATALOG_PRODUCT_ATTRIBUTES = [
        'Formazione' => [
            ['code' => self::TOTAL_HOURS_ATTRIBUTE, 'name' => 'Ore complessive', 'type' => 'integer'],
            ...ClassroomAttributeCatalogue::ATTRIBUTES,
        ],
        SelfFundedCourseCatalogue::CATEGORY => [
            ['code' => self::DELIVERY_MODE_ATTRIBUTE, 'name' => 'Modalità di svolgimento', 'type' => 'enum', 'options' => [
                ['value' => SelfFundedCourseCatalogue::IN_PERSON, 'label' => 'In presenza'],
                ['value' => SelfFundedCourseCatalogue::ONLINE, 'label' => 'Online'],
            ]],
        ],
    ];

    /**
     * `attribute_values` key (an Attribute `code`) holding a training course's
     * duration — shared by the definition above and by the course seed.
     */
    private const string TOTAL_HOURS_ATTRIBUTE = 'total_hours';

    /**
     * `attribute_values` key (an Attribute `code`) holding a self-funded
     * course's delivery mode — shared by the definition above and by the
     * course seed.
     */
    private const string DELIVERY_MODE_ATTRIBUTE = 'delivery_mode';

    /**
     * @param  bool  $askForLegacyImport  Offer to chain the q-crm import once
     *                                    the catalogue is in place. True when
     *                                    this seeder is launched on its own —
     *                                    that import is the natural next step
     *                                    and it depends on what lands here.
     *                                    QualificaProductionDataSeeder passes
     *                                    false: it runs the import itself, as
     *                                    its own step 4.
     */
    public function run(bool $askForLegacyImport = true): void
    {
        // Step 1: the standalone pick-lists, in no particular order.
        $this->seedSources();
        $this->seedRewardTypes();

        // Step 2: the category tree and the attribute the courses below need.
        $this->seedCatalog();

        // Step 3: the courses, which resolve their category from step 2 and
        // their attributes from the assignments it sets up.
        $this->seedTrainingCourses();
        $this->seedSelfFundedCourses();

        // Step 4: the "stati di lavorazione", which key their matching
        // criterion on the categories of step 2.
        $this->call(QualificaWorkflowSeeder::class);

        // Step 4-bis: the "Dati Aula" form section, which places the
        // attributes step 2 assigned onto every category of the branch.
        $this->call(QualificaClassroomLayoutSeeder::class);

        // Step 4-ter: the Opportunity-context set, which resolves the same
        // categories of step 2 and adopts the q-crm rows when they are there.
        $this->call(QualificaContactProcessingSeeder::class);

        // Step 5: the optional follow-up, on demand.
        if ($askForLegacyImport) {
            $this->offerLegacyImport();
        }

        // Step 6: the business function link, LAST — the function it looks for
        // comes from the import above, not from this catalogue. A no-op, never
        // an error, when that import did not run or has no such function;
        // QualificaProductionDataSeeder repeats it after its own import.
        $this->call(QualificaBusinessFunctionLinkSeeder::class);
    }

    /**
     * Ask whether to pull the configuration tables from q-crm as well — the
     * step that must run right after this one (it adopts the sources seeded
     * above and nests its taxonomy under the "Consulenza" root).
     *
     * Silent, and never importing, when there is nobody to ask — a programmatic
     * `$this->call()`, or a `--no-interaction` run (`test()->seed()` included:
     * it always passes that flag) — and when there is nothing to ask about, no
     * external system being configured makes the import a documented no-op.
     * The prompt itself defaults to NO.
     */
    private function offerLegacyImport(): void
    {
        if ($this->command === null || $this->command->option('no-interaction')) {
            return;
        }

        if (blank(config('migrations.base_url'))) {
            return;
        }

        if (! $this->command->confirm('Importare anche le tabelle di configurazione da q-crm?', false)) {
            return;
        }

        $this->call(QualificaLegacyImportSeeder::class);
    }

    private function seedSources(): void
    {
        foreach (self::SOURCES as $name) {
            Source::firstOrCreate(['name' => $name]);
        }
    }

    private function seedRewardTypes(): void
    {
        foreach (self::REWARD_TYPES as $name => $color) {
            RewardType::firstOrCreate(['name' => $name], ['color' => $color]);
        }
    }

    private function seedCatalog(): void
    {
        foreach (self::CATALOG as $rootName => $subcategories) {
            // A root always parents subcategories, so it is a container by
            // construction (spec 0074): never a classification target.
            $root = $this->seedCatalogCategory($rootName, null, isSelectable: false, realign: true);

            foreach ($subcategories as $subName => $childNames) {
                // A subcategory is a container TOO, whether or not it already
                // has children (user directive 2026-08-03): the catalogue
                // classifies on its third level, so an empty child list means
                // "no target seeded here yet", not "this node is the target".
                // Unless it hosts its own offer — see SELECTABLE_SUBCATEGORIES.
                $isSelectable = in_array($subName, self::SELECTABLE_SUBCATEGORIES, true);

                $subcategory = $this->seedCatalogCategory($subName, $root->id, $isSelectable, realign: true);
                $this->seedCatalogChildren($subcategory, $childNames);
            }
        }

        // The attributes come after the WHOLE tree: an assignment can target
        // any node, at any depth, not just the root being built above.
        foreach (self::CATALOG_PRODUCT_ATTRIBUTES as $categoryName => $specs) {
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();
            $this->seedCategoryAttributes($category, $specs, AttributeContext::Product);
        }

        $this->seedCatalogManagementModes();
    }

    private function seedCatalogManagementModes(): void
    {
        $inheritance = app(CategoryManagementModeInheritance::class);
        foreach (self::CATALOG_MANAGEMENT_MODES as $rootName => $mode) {
            $root = ProductCategory::query()->where('name', $rootName)->whereNull('parent_id')->firstOrFail();
            if ($root->management_mode !== $mode) {
                $root->update(['management_mode' => $mode]);
            }
            $inheritance->syncSubtree($root);
        }
    }

    /**
     * @param  list<string>  $childNames
     */
    private function seedCatalogChildren(ProductCategory $parent, array $childNames): void
    {
        foreach ($childNames as $childName) {
            $this->seedCatalogCategory($childName, $parent->id, isSelectable: true, realign: false);
        }
    }

    /**
     * One catalogue node, idempotent on `name` (the catalogue's natural key).
     *
     * The nodes the catalogue DECLARES itself — the roots and their
     * subcategories (spec 0074) — have their `is_selectable` REALIGNED on every
     * run, in both directions, not just written at creation: an installation
     * seeded before the flag existed must actually see its mother categories
     * stop being classification targets, and one seeded while "Autofinanziato"
     * was still filed as a container must see it become a target again. A plain
     * `firstOrCreate` would silently skip both.
     *
     * A leaf is only ever given the default on creation and never realigned —
     * the catalogue declares the shape of its own two levels, it does not claim
     * authority over an operator's decision to retire a leaf.
     */
    private function seedCatalogCategory(string $name, ?int $parentId, bool $isSelectable, bool $realign): ProductCategory
    {
        /** @var ProductCategory $category */
        $category = ProductCategory::firstOrCreate(
            ['name' => $name],
            ['parent_id' => $parentId, 'is_selectable' => $isSelectable],
        );

        if ($realign && $category->is_selectable !== $isSelectable) {
            $category->update(['is_selectable' => $isSelectable]);
        }

        return $category;
    }

    /**
     * The GOL training courses (TrainingCourseCatalogue): one product per row,
     * in its own region's `GOL - <Regione>` category, carrying its duration in
     * the `total_hours` attribute the Formazione root hands down.
     */
    private function seedTrainingCourses(): void
    {
        $service = app(ProductService::class);

        foreach (TrainingCourseCatalogue::COURSES as $categoryName => $courses) {
            // The category is created by seedCatalog() above: a miss means the
            // two lists drifted apart, which must fail loudly rather than
            // silently drop a whole region's courses.
            $category = ProductCategory::query()->where('name', $categoryName)->firstOrFail();

            foreach ($this->disambiguate($courses) as $course) {
                // Cost/price stay 0: a funded course is not sold to the learner.
                $this->seedCourse($service, $category, $course['name'], 0.0, [
                    self::TOTAL_HOURS_ATTRIBUTE => $course['hours'],
                ]);
            }
        }
    }

    /**
     * The self-funded courses (SelfFundedCourseCatalogue): one product per row
     * under the single "Autofinanziato" subcategory, priced, carrying its
     * duration in the inherited `total_hours` and its delivery mode in the
     * `delivery_mode` attribute assigned to that subcategory.
     */
    private function seedSelfFundedCourses(): void
    {
        $service = app(ProductService::class);

        // Created by seedCatalog() above: a miss means the two lists drifted
        // apart, which must fail loudly rather than silently drop the courses.
        $category = ProductCategory::query()->where('name', SelfFundedCourseCatalogue::CATEGORY)->firstOrFail();

        foreach (SelfFundedCourseCatalogue::COURSES as $course) {
            $this->seedCourse($service, $category, $course['name'], $course['price'], [
                self::TOTAL_HOURS_ATTRIBUTE => $course['hours'],
                self::DELIVERY_MODE_ATTRIBUTE => $course['delivery_mode'],
            ]);
        }
    }

    /**
     * A course name repeating inside one region is a DISTINCT course with its
     * own duration (user decision 2026-07-27): every occurrence of a repeated
     * name takes an "(N ore)" suffix — the duration is the only discriminator
     * the source list carries — so the two survive as separate products
     * instead of collapsing onto the same natural key. A name occurring once
     * is left untouched.
     *
     * @param  list<array{name: string, hours: int}>  $courses
     * @return list<array{name: string, hours: int}>
     */
    private function disambiguate(array $courses): array
    {
        $occurrences = array_count_values(array_column($courses, 'name'));

        return array_map(
            static fn (array $course): array => $occurrences[$course['name']] > 1
                ? ['name' => sprintf('%s (%d ore)', $course['name'], $course['hours']), 'hours' => $course['hours']]
                : $course,
            $courses,
        );
    }

    /**
     * @param  array<string, mixed>  $attributeValues
     */
    private function seedCourse(
        ProductService $service,
        ProductCategory $category,
        string $name,
        float $price,
        array $attributeValues,
    ): void {
        // Natural key (name, category) — scoped to the category because the
        // SAME course runs in several regions. An already-seeded course is
        // left untouched, so a manual edit survives the re-run.
        $exists = Product::query()
            ->where('name', $name)
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            return;
        }

        $service->create(new CreateProductData(
            name: $name,
            description: null,
            // Cost is filled in later through the CRUD modules.
            cost: 0.0,
            price: $price,
            categoryId: $category->id,
            productType: ProductType::Service,
            attributeValues: $attributeValues,
        ));
    }
}
