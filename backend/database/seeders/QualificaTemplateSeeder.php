<?php

namespace Database\Seeders;

use App\DataObjects\Products\CreateProductData;
use App\Enums\AttributeContext;
use App\Enums\ProductType;
use App\Models\Attribute;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldOption;
use App\Models\CustomFieldValue;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RewardType;
use App\Models\Source;
use App\Services\ProductService;
use Database\Seeders\QualificaTemplate\TrainingCourseCatalogue;
use Illuminate\Database\Seeder;

/**
 * Clean, idempotent reference seed: provisions the per-module custom field
 * "template" as universal custom field definitions (spec 0021). One entry per
 * entity_type in TEMPLATES:
 *   - company-sites: the former flat "Altro" columns, PLUS the former
 *     client-specific ERP settings (responsible_*, proforma/invoice
 *     progressives, quotation_*), now dynamic fields;
 *   - products: the validity in months and the filing folder.
 *
 * Plus the client's source catalogue (spec 0018): the fixed provenance list
 * used to classify registry/lead/opportunity records.
 *
 * Plus the client's reward type catalogue (spec 0058): the voucher/reward
 * types actually in use.
 *
 * Plus the client's reference category catalogue (spec 0017): a two-root
 * category tree (Formazione / Consulenza) with its subcategories and the
 * regional declinations under GOL. The "Formazione" branch also carries a
 * product-context attribute ("Ore complessive", spec 0061), assigned to the
 * root and inherited by every descendant.
 *
 * Plus the GOL training courses (Database\Seeders\QualificaTemplate\
 * TrainingCourseCatalogue): one SERVICE product per funded course, filed under
 * its own region's `GOL - <Regione>` category and carrying its duration in
 * that attribute. Cost/price stay 0 — they are edited later through the CRUD
 * modules. No other product is seeded.
 *
 * Custom-field/source definitions write no per-row values (that is user data);
 * the catalogue does create ProductCategory/Product rows, all idempotent:
 * `updateOrCreate` on (entity_type, key) for custom fields, `firstOrCreate` on
 * the natural name key for sources, reward types, categories and courses — a
 * re-run never duplicates rows nor overwrites manual edits. Adding a module's
 * template = one more entry in TEMPLATES.
 *
 * What is NOT here: the legacy import. QualificaLegacyImportSeeder is a
 * separate, standalone step (`php artisan db:seed
 * --class=QualificaLegacyImportSeeder`), run AFTER this one — it adopts the
 * source catalogue provisioned below and nests its imported taxonomy under the
 * "Consulenza" root created below.
 */
class QualificaTemplateSeeder extends Seeder
{
    /**
     * `accounting_manager_id` points at a single user (the former
     * `accounting_manager_id` FK). `users` is a registered custom-fieldable
     * entity, so it is a valid relation target + for-select resource.
     *
     * @var array<string, mixed>
     */
    private const array MANAGER_RELATION_TARGET = [
        'entity_type' => 'users',
        'cardinality' => 'one',
        'for_select_resource' => 'users',
    ];

    /**
     * entity_type => ordered list of field specs [key, label, type, ?relation_target].
     * Everything company-site is `integer` (former numeric reference/status
     * columns) except `color` (free text) and `accounting_manager_id` (a
     * one-to-one relation to a user). On products the expiration is a
     * duration in months (`integer`), not a fixed date, and the folder is an
     * `enum` whose discrete options are seeded with the definition.
     *
     * @var array<string, list<array{key: string, label: string, type: string, relation_target?: array<string, mixed>}>>
     */
    private const array TEMPLATES = [
        'company-sites' => [
            ['key' => 'accounting_manager_id', 'label' => 'Responsabile amministrativo', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'store_id', 'label' => 'Negozio', 'type' => 'integer'],
            ['key' => 'company_type', 'label' => 'Tipo società', 'type' => 'integer'],
            ['key' => 'commissions', 'label' => 'Commissioni', 'type' => 'integer'],
            ['key' => 'order_sites', 'label' => 'Ordine sedi', 'type' => 'integer'],
            ['key' => 'payment_status_assign_technician', 'label' => 'Stato pagamento (assegna tecnico)', 'type' => 'integer'],
            ['key' => 'payment_status_deposit', 'label' => 'Stato pagamento (acconto)', 'type' => 'integer'],
            ['key' => 'payment_status_balance', 'label' => 'Stato pagamento (saldo)', 'type' => 'integer'],
            ['key' => 'default_payment_id', 'label' => 'Pagamento predefinito', 'type' => 'integer'],
            ['key' => 'default_vat_id', 'label' => 'IVA predefinita', 'type' => 'integer'],
            ['key' => 'other_category_id', 'label' => 'Categoria altro', 'type' => 'integer'],
            ['key' => 'iso_category_id', 'label' => 'Categoria ISO', 'type' => 'integer'],
            ['key' => 'soa_category_id', 'label' => 'Categoria SOA', 'type' => 'integer'],
            ['key' => 'sic_category_id', 'label' => 'Categoria SIC', 'type' => 'integer'],
            ['key' => 'avv_category_id', 'label' => 'Categoria AVV', 'type' => 'integer'],
            ['key' => 'gdpr_category_id', 'label' => 'Categoria GDPR', 'type' => 'integer'],
            ['key' => 'res_category_id', 'label' => 'Categoria RES', 'type' => 'integer'],
            ['key' => 'pal_category_id', 'label' => 'Categoria PAL', 'type' => 'integer'],
            ['key' => 'quattro_category_id', 'label' => 'Categoria 4.0', 'type' => 'integer'],
            ['key' => 'finage_category_id', 'label' => 'Categoria Finage', 'type' => 'integer'],
            ['key' => 'fondi_category_id', 'label' => 'Categoria fondi', 'type' => 'integer'],
            ['key' => 'gare_category_id', 'label' => 'Categoria gare', 'type' => 'integer'],
            ['key' => 'partnership_category_id', 'label' => 'Categoria partnership', 'type' => 'integer'],
            ['key' => 'progetti_category_id', 'label' => 'Categoria progetti', 'type' => 'integer'],
            ['key' => 'status', 'label' => 'Stato', 'type' => 'integer'],
            ['key' => 'color', 'label' => 'Colore', 'type' => 'text'],
            ['key' => 'surface_sqm', 'label' => 'Superficie (mq)', 'type' => 'integer'],
            // De-verticalization: former `responsible_*_id` FKs and ERP
            // settings columns (proforma/invoice progressives, quotation_*),
            // now dynamic fields.
            ['key' => 'responsible_rda', 'label' => 'Responsabile RDA', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_tickets', 'label' => 'Responsabile Ticket', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_validation_contracts', 'label' => 'Responsabile Validazione contratti', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'responsible_validation_contracts_two', 'label' => 'Responsabile Validazione contratti 2', 'type' => 'relation', 'relation_target' => self::MANAGER_RELATION_TARGET],
            ['key' => 'proforma_progressive', 'label' => 'Progressivo proforma', 'type' => 'integer'],
            ['key' => 'invoice_progressive', 'label' => 'Progressivo fattura', 'type' => 'integer'],
            ['key' => 'quotation_layout', 'label' => 'Layout preventivo', 'type' => 'integer'],
            ['key' => 'quotation_header', 'label' => 'Header preventivo', 'type' => 'integer'],
            ['key' => 'quotation_footer', 'label' => 'Footer preventivo', 'type' => 'integer'],
        ],
        'products' => [
            ['key' => 'expiration_months', 'label' => 'Mesi scadenza', 'type' => 'integer'],
            ['key' => 'folder', 'label' => 'Cartella', 'type' => 'enum', 'options' => [
                ['value' => 'ente', 'label' => 'Ente'],
                ['value' => 'consulenza', 'label' => 'Consulenza'],
            ]],
        ],
    ];

    /**
     * Template fields replaced by a later revision: the definition is dropped
     * and the key stripped from the stored JSON payloads, so a re-seed
     * converges instead of leaving an orphan field on the module.
     * `products.expiration_date` (a date) became `expiration_months` (a
     * duration): the old values are NOT convertible, hence discarded.
     *
     * @var array<string, list<string>>
     */
    private const array SUPERSEDED_FIELDS = [
        'products' => ['expiration_date'],
    ];

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
     * root because `inherits_attributes` defaults to true and a category's
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

    public function run(): void
    {
        foreach (self::TEMPLATES as $entityType => $fields) {
            $this->seedTemplate($entityType, $fields);
        }

        $this->pruneSupersededFields();

        $this->seedSources();
        $this->seedRewardTypes();
        $this->seedCatalog();
        $this->seedTrainingCourses();
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

    /**
     * @param  list<array{key: string, label: string, type: string, relation_target?: array<string, mixed>, options?: list<array{value: string, label: string}>}>  $fields
     */
    private function seedTemplate(string $entityType, array $fields): void
    {
        $sortOrder = 0;

        foreach ($fields as $field) {
            $definition = CustomFieldDefinition::updateOrCreate(
                ['entity_type' => $entityType, 'key' => $field['key']],
                [
                    'type' => $field['type'],
                    'label' => $field['label'],
                    'sort_order' => $sortOrder++,
                    'is_indexed' => false,
                    'is_active' => true,
                    'relation_target' => $field['relation_target'] ?? null,
                ],
            );

            $this->seedOptions($definition, $field['options'] ?? []);
        }
    }

    /**
     * @param  list<array{value: string, label: string}>  $options
     */
    private function seedOptions(CustomFieldDefinition $definition, array $options): void
    {
        $sortOrder = 0;

        foreach ($options as $option) {
            CustomFieldOption::updateOrCreate(
                ['definition_id' => $definition->id, 'value' => $option['value']],
                ['label' => $option['label'], 'sort_order' => $sortOrder++],
            );
        }
    }

    private function pruneSupersededFields(): void
    {
        foreach (self::SUPERSEDED_FIELDS as $entityType => $keys) {
            // Step 1: drop the definitions (options cascade on delete).
            CustomFieldDefinition::query()
                ->where('entity_type', $entityType)
                ->whereIn('key', $keys)
                ->get()
                ->each->delete();

            // Step 2: strip the keys from the stored per-entity payloads, so
            // no value survives its definition.
            CustomFieldValue::query()
                ->where('entity_type', $entityType)
                ->each(function (CustomFieldValue $row) use ($keys): void {
                    $values = (array) $row->values;

                    if (empty(array_intersect_key($values, array_flip($keys)))) {
                        return;
                    }

                    $row->update(['values' => array_diff_key($values, array_flip($keys))]);
                });
        }
    }
}
