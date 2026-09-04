<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Database\Seeders\QualificaCatalog\CatalogProducts;
use Database\Seeders\QualificaCatalog\CatalogRootRules;
use Database\Seeders\QualificaCatalog\ClassroomAttributeCatalogue;
use Database\Seeders\QualificaCatalog\SelfFundedCourseCatalogue;
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
 *     rows — plus the subcategories that host their offer directly,
 *     "Autofinanziato", "Autoimpiego" and "Yisu" (see
 *     SELECTABLE_SUBCATEGORIES). The "Formazione" branch also carries its
 *     product-context attributes (spec 0061) — "Ore complessive" and the
 *     "Dati Aula" set of QualificaCatalog\ClassroomAttributeCatalogue —
 *     assigned to the root and inherited by every descendant, then grouped
 *     into form sections by QualificaClassroomLayoutSeeder (spec 0062). The
 *     counterpart of the OFFERTA and COMMESSA contexts ("Dati Lavorazione
 *     Contatto", scoped to Formazione / Autofinanziato / the two Consulenza
 *     leaves) is delegated to QualificaContactProcessingSeeder;
 *   - every product of the catalogue, delegated to
 *     QualificaCatalog\CatalogProducts once the tree exists: the GOL courses
 *     under their own region, the self-funded ones under "Autofinanziato"
 *     with their price and delivery mode, and one product named after each
 *     single-offer subcategory ("Autoimpiego", "Yisu"). No other product is
 *     seeded;
 *   - the ROOT-OWNED rules of the two roots (how many product lines a card
 *     carries, how many offers an opportunity may hold), delegated to
 *     QualificaCatalog\CatalogRootRules once the whole tree exists — it
 *     re-syncs each branch;
 *   - the "stati di lavorazione" (spec 0047), delegated to
 *     QualificaWorkflowSeeder as the last step: one QuoteWorkflow per
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
     * directive 2026-08-03). CatalogProducts::SINGLE_OFFER_SUBCATEGORIES are the others, for
     * the same reason. Bound by identity to the catalogues that file the
     * products, so a rename breaks loudly instead of silently demoting a node.
     *
     * @var list<string>
     */
    private const array SELECTABLE_SUBCATEGORIES = [
        SelfFundedCourseCatalogue::CATEGORY,
        ...CatalogProducts::SINGLE_OFFER_SUBCATEGORIES,
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
            ['code' => CatalogProducts::TOTAL_HOURS_ATTRIBUTE, 'name' => 'Ore complessive', 'type' => 'integer'],
            ...ClassroomAttributeCatalogue::ATTRIBUTES,
        ],
        SelfFundedCourseCatalogue::CATEGORY => [
            ['code' => CatalogProducts::DELIVERY_MODE_ATTRIBUTE, 'name' => 'Modalità di svolgimento', 'type' => 'enum', 'options' => [
                ['value' => SelfFundedCourseCatalogue::IN_PERSON, 'label' => 'In presenza'],
                ['value' => SelfFundedCourseCatalogue::ONLINE, 'label' => 'Online'],
            ]],
        ],
    ];

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

        // Step 3: the products, which resolve their category from step 2 and
        // their attributes from the assignments it sets up.
        app(CatalogProducts::class)->seed();

        // Step 4: the "stati di lavorazione", which key their matching
        // criterion on the categories of step 2.
        $this->call(QualificaWorkflowSeeder::class);

        // Step 4-bis: the "Dati Aula" form section, which places the
        // attributes step 2 assigned onto every category of the branch.
        $this->call(QualificaClassroomLayoutSeeder::class);

        // Step 4-ter: the Offerta/Commessa set, which resolves the same
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

        // The root-owned rules come last: they re-sync the whole subtree, so
        // every node must already exist.
        app(CatalogRootRules::class)->apply();
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
}
