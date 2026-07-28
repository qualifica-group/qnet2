<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\AttributeContext;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use App\Services\ProductService;
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
 *     declinations under GOL. The "Formazione" branch also carries a
 *     product-context attribute ("Ore complessive", spec 0061), assigned to
 *     the root and inherited by every descendant;
 *   - the GOL training courses (QualificaCatalog\TrainingCourseCatalogue): one
 *     SERVICE product per funded course, filed under its own region's
 *     `GOL - <Regione>` category and carrying its duration in that attribute.
 *     Cost/price stay 0 — they are edited later through the CRUD modules. No
 *     other product is seeded.
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
    /**
     * The client's fixed source catalogue (spec 0018): user-facing domain
     * values, kept in their original language. Seeded in order.
     *
     * @var list<string>
     */
    private const array SOURCES = [
        'Diretto',
        'Passaparola',
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
     * (subcategory => list of leaf children, empty when the subcategory is
     * itself a leaf). The tree has no depth limit. Names are user-facing
     * domain values, kept in their original language, and are the natural
     * keys used for idempotent `firstOrCreate` on re-run.
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
     * Product-context attributes (spec 0061) assigned to a ROOT category:
     * root name => list of catalogue attribute specs. Assigned once at the
     * root because `inherits_product_attributes` defaults to true and a category's
     * EFFECTIVE attributes are its own UNION every ancestor's — so the whole
     * "Formazione" branch (its subcategories AND the regional GOL children)
     * gets the field from a single pivot row, and a subcategory added later
     * is covered automatically.
     *
     * `code` is the English identifier (the catalogue's natural key, and its
     * `^[a-z0-9_]+$` format); `name` is the user-facing label, kept in its
     * original language. `type` is a FieldTypeRegistry key.
     *
     * @var array<string, list<array{code: string, name: string, type: string}>>
     */
    private const array CATALOG_PRODUCT_ATTRIBUTES = [
        'Formazione' => [
            ['code' => self::TOTAL_HOURS_ATTRIBUTE, 'name' => 'Ore complessive', 'type' => 'integer'],
        ],
    ];

    /**
     * `attribute_values` key (an Attribute `code`) holding a training course's
     * duration — shared by the definition above and by the course seed.
     */
    private const string TOTAL_HOURS_ATTRIBUTE = 'total_hours';

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
        // their attribute from the inheritance it sets up.
        $this->seedTrainingCourses();

        // Step 4: the optional follow-up, on demand.
        if ($askForLegacyImport) {
            $this->offerLegacyImport();
        }
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
            $root = ProductCategory::firstOrCreate(['name' => $rootName], ['parent_id' => null]);
            $this->seedProductAttributes($root, self::CATALOG_PRODUCT_ATTRIBUTES[$rootName] ?? []);

            foreach ($subcategories as $subName => $childNames) {
                $subcategory = ProductCategory::firstOrCreate(['name' => $subName], ['parent_id' => $root->id]);
                $this->seedCatalogChildren($subcategory, $childNames);
            }
        }
    }

    /**
     * @param  list<string>  $childNames
     */
    private function seedCatalogChildren(ProductCategory $parent, array $childNames): void
    {
        foreach ($childNames as $childName) {
            ProductCategory::firstOrCreate(['name' => $childName], ['parent_id' => $parent->id]);
        }
    }

    /**
     * @param  list<array{code: string, name: string, type: string}>  $specs
     */
    private function seedProductAttributes(ProductCategory $category, array $specs): void
    {
        foreach ($specs as $spec) {
            // Natural key (code): an attribute already in the catalogue keeps
            // its label/type, so a manual rename survives the re-seed.
            $attribute = Attribute::firstOrCreate(
                ['code' => $spec['code']],
                ['name' => $spec['name'], 'type' => $spec['type']],
            );

            $this->assignProductAttribute($category, $attribute);
        }
    }

    /**
     * Additive on purpose, unlike ProductCategoryService::syncAttributes()
     * which is a full replace: a re-seed must not wipe the assignments made
     * by hand from the category configurator.
     */
    private function assignProductAttribute(ProductCategory $category, Attribute $attribute): void
    {
        $isAssigned = $category->attributes()
            ->wherePivot('context', AttributeContext::Product->value)
            ->where('attributes.id', $attribute->id)
            ->exists();

        if ($isAssigned) {
            return;
        }

        $category->attributes()->attach($attribute->id, [
            'context' => AttributeContext::Product->value,
            'is_required' => false,
            'sort_order' => 0,
        ]);
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
                $this->seedTrainingCourse($service, $category, $course);
            }
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
     * @param  array{name: string, hours: int}  $course
     */
    private function seedTrainingCourse(ProductService $service, ProductCategory $category, array $course): void
    {
        // Natural key (name, category) — scoped to the category because the
        // SAME course runs in several regions. An already-seeded course is
        // left untouched, so a manual edit survives the re-run.
        $exists = Product::query()
            ->where('name', $course['name'])
            ->where('category_id', $category->id)
            ->exists();

        if ($exists) {
            return;
        }

        $service->create(new CreateProductData(
            name: $course['name'],
            description: null,
            cost: 0.0,
            price: 0.0,
            categoryId: $category->id,
            productType: ProductType::Service,
            attributeValues: [self::TOTAL_HOURS_ATTRIBUTE => $course['hours']],
        ));
    }
}
