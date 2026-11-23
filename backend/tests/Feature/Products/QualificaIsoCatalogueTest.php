<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\RequestManagement\AttributeSectionVisibility;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\IsoRequestedStandardsSeeder;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Database\Seeders\QualificaCatalog\IsoMultiStandardProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoNamedStandardProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoSubcategories;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaIsoQuoteFormSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "ISO" branch (user directive 2026-10-21): a root linked to the "ISO"
// business function, worked like "Consulenza", with the legacy "Informazioni
// Preliminari Iso" as offer fields — one checkbox per norm, a group block
// opening when any of its norms is ticked (spec 0424) — and the legacy ISO
// Commessa blocks, always visible.
// Its subcategories and products come from the "iso" service list.
uses(RefreshDatabase::class);

/** A subcategory of the list, standing for all of them in the form checks. */
const ISO_SAMPLE_CATEGORY = 'ISO - 9001';

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

function isoCategory(string $name): ProductCategory
{
    return ProductCategory::query()->where('name', $name)->sole();
}

/**
 * @param  list<array<string, mixed>>  $sections
 * @return array<string, list<string>> section id => its attribute codes
 */
function isoSectionCodes(array $sections): array
{
    return collect($sections)->mapWithKeys(static fn (array $section): array => [
        $section['id'] => array_merge(...array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        )),
    ])->all();
}

it('seeds the ISO root with the selectable subcategories of the list, the ISO function and the Consulenza rules, idempotently', function (): void {
    // The legacy import brings the function, in its own case: adopted, not duplicated.
    $imported = BusinessFunction::factory()->create(['name' => 'iso']);

    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = isoCategory(IsoCatalogue::ROOT);
    $subcategories = ProductCategory::query()->where('parent_id', $root->id)->get();

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($imported->id)
        ->and(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['iso'])->count())->toBe(1)
        ->and($subcategories->pluck('name')->all())->toEqualCanonicalizing(IsoSubcategories::TARGET_CATEGORIES)
        ->and(IsoSubcategories::TARGET_CATEGORIES)->toHaveCount(89)
        ->and(array_keys(IsoSubcategories::SUBCATEGORIES))->toBe(IsoSubcategories::TARGET_CATEGORIES);

    foreach ($subcategories as $category) {
        expect($category->is_selectable)->toBeTrue($category->name)
            ->and($category->business_function_id)->toBeNull($category->name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($imported->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->single_quote_per_opportunity)->toBeFalse()
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives a subcategory the root offer fields and a form whose group blocks open on any of their norms', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = isoCategory(ISO_SAMPLE_CATEGORY);
    $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];
    $ownCodes = collect(IsoCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();
    $codesBySection = isoSectionCodes($sections);

    expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe($ownCodes)
        ->and(array_merge(...array_values($codesBySection)))->toEqualCanonicalizing($ownCodes)
        ->and(AttributeLayout::query()->where('product_category_id', $category->id)->exists())->toBeFalse()
        ->and(collect($sections)->mapWithKeys(fn (array $section): array => [$section['id'] => $section['visible_when']['option_values'] ?? null])->filter()->all())
        ->toBe(IsoCatalogue::GROUP_STANDARDS)
        ->and(IsoCatalogue::GROUP_STANDARDS['iso-group-general'])->toContain('standard_9001', 'standard_sa8000', 'standard_13549', 'standard_ecolabel')
        ->and(IsoCatalogue::GROUP_STANDARDS['iso-group-food'])->toBe(['standard_brc', 'standard_ifs', 'standard_22000', 'standard_22005', 'standard_sa8000', 'standard_13549'])
        ->and(collect($sections)->pluck('visible_when.attribute_code')->filter()->unique()->values()->all())->toBe([IsoCatalogue::STANDARDS])
        ->and($codesBySection['iso-standards'])->toBe([IsoCatalogue::STANDARDS, 'iso_other_standard'])
        ->and(Attribute::query()->where('code', 'iso_other_standard')->sole()->only(['name', 'type', 'config']))
        ->toBe(['name' => 'Altro', 'type' => 'text', 'config' => null])
        ->and(Attribute::query()->where('code', IsoCatalogue::STANDARDS)->sole()->only(['name', 'type', 'config']))
        ->toBe(['name' => 'Norme richieste', 'type' => 'enum', 'config' => ['display' => 'checklist']])
        ->and(Attribute::query()->where('code', IsoCatalogue::STANDARDS)->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['9001', '14001', '45001', '37001', '39001', '27001', '50001', 'SA8000', '13549', 'Parità di genere + ISO 30415', 'BRC', 'IFS', '22000', '22005', 'EMAS', 'Ecolabel', '1090', '16636'])
        ->and(Attribute::query()->where('code', 'iso_g1_certification_type')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Prima certificazione', 'Estensione di scopo', 'Estensione di sito', 'Ricertificazione', 'Trasferimento di certificato', 'Variazione di certificato']);
});

it('brings an installation already seeded up to the ISO offer fields and form, keeping the offer values', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $root = isoCategory(IsoCatalogue::ROOT);
    $quote = Quote::factory()->create(['attribute_values' => [IsoCatalogue::STANDARDS => ['standard_9001'], 'iso_g1_notes' => 'Kept']]);

    // An installation seeded before "Altro": the same form without it, and no such field.
    $older = $layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All);
    unset($older['sections'][0]['rows'][1]);
    $layouts->upsert($root, AttributeContext::Quote, LayoutFormScope::All, $older);
    Attribute::query()->where('code', 'iso_other_standard')->sole()->delete();

    // A plain re-seed brings the field back but leaves a configured form alone...
    test()->seed(QualificaCatalogSeeder::class);
    expect(isoSectionCodes($layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All)['sections'])['iso-standards'])
        ->not->toContain('iso_other_standard');

    // ...the targeted one rewrites it, twice to the same result.
    test()->seed(QualificaIsoQuoteFormSeeder::class);
    test()->seed(QualificaIsoQuoteFormSeeder::class);

    $sections = $layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All)['sections'];

    expect(array_column($sections, 'id'))->toBe(array_column(IsoCatalogue::QUOTE_SECTIONS, 0))
        ->and(isoSectionCodes($sections)['iso-standards'])->toContain('iso_other_standard')
        ->and(Attribute::query()->where('code', 'iso_other_standard')->count())->toBe(1)
        ->and(app(CategoryHierarchy::class)->effectiveAttributes(isoCategory(ISO_SAMPLE_CATEGORY), AttributeContext::Quote)->pluck('code'))
        ->toContain('iso_other_standard')
        ->and($quote->fresh()->attribute_values)->toBe([IsoCatalogue::STANDARDS => ['standard_9001'], 'iso_g1_notes' => 'Kept']);
});

it('hides a group block, its certification type select included, until one of its norms is ticked', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layout = app(AttributeLayoutService::class)->resolveWithFallback(isoCategory(ISO_SAMPLE_CATEGORY), AttributeContext::Quote, FormMode::Create);
    $visibility = app(AttributeSectionVisibility::class);

    $hidden = $visibility->hiddenCodes($layout, [IsoCatalogue::STANDARDS => ['standard_50001', 'standard_39001']]);

    expect($hidden)->toContain('iso_g1_certification_type', 'iso_g2_certification_type', 'iso_g4_certification_type', 'iso_g6_managers')
        ->and($hidden)->not->toContain('iso_g3_certification_type', 'iso_g3_electricity_consumption', 'iso_g5_drivers', 'iso_application_scope', IsoCatalogue::STANDARDS)
        // SA8000 opens both the general and the food block, as in the legacy.
        ->and($visibility->hiddenCodes($layout, [IsoCatalogue::STANDARDS => ['standard_sa8000']]))->not->toContain('iso_g1_total_employees', 'iso_g2_surface_sqm');
});

/**
 * The ISO offer form as seeded before spec 0424: $retired yes/no fields (or
 * one list of groups when $retired is null), each opening its group block.
 */
function isoFormBefore0424(ProductCategory $root, ?array $retired): void
{
    $layouts = app(AttributeLayoutService::class);
    $older = $layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All);
    $blockOf = array_flip($retired ?? IsoRequestedStandardsSeeder::RETIRED_GROUP_ENTRIES);

    if ($retired === null) {
        $list = Attribute::factory()->create(['code' => IsoRequestedStandardsSeeder::RETIRED_GROUP_LIST, 'type' => 'enum', 'config' => ['display' => 'checklist']]);
        foreach (array_keys(IsoRequestedStandardsSeeder::RETIRED_GROUP_ENTRIES) as $index => $entry) {
            $list->options()->create(['value' => $entry, 'label' => $entry, 'sort_order' => $index]);
        }
        $root->attributes()->attach($list->id, ['context' => 'quote', 'is_required' => false, 'sort_order' => 0]);
        $older['sections'][0]['rows'][0]['items'] = [['attribute_code' => $list->code, 'width' => 'full']];
    } else {
        foreach (array_keys($retired) as $index => $code) {
            $root->attributes()->attach(Attribute::factory()->create(['code' => $code, 'type' => 'boolean'])->id, ['context' => 'quote', 'is_required' => false, 'sort_order' => $index]);
        }
        $older['sections'][0]['rows'][0]['items'] = array_map(static fn (string $code): array => ['attribute_code' => $code, 'width' => 'third'], array_keys($retired));
    }

    foreach ($older['sections'] as $index => $section) {
        if (isset($section['visible_when'])) {
            $older['sections'][$index]['visible_when'] = $retired === null
                ? ['attribute_code' => IsoRequestedStandardsSeeder::RETIRED_GROUP_LIST, 'option_values' => [$blockOf[$section['id']]]]
                : ['attribute_code' => $blockOf[$section['id']]];
        }
    }

    $layouts->upsert($root, AttributeContext::Quote, LayoutFormScope::All, $older);
}

function isoQuoteWithProducts(array $values, array $productNames = []): Quote
{
    $quote = Quote::factory()->create(['attribute_values' => $values]);

    foreach ($productNames as $name) {
        QuoteLine::factory()->create(['quote_id' => $quote->id, 'product_id' => Product::factory()->create(['name' => $name])->id]);
    }

    return $quote;
}

it('moves an installation from the six group checkboxes to one checkbox per norm, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    $root = isoCategory(IsoCatalogue::ROOT);
    isoFormBefore0424($root, IsoRequestedStandardsSeeder::RETIRED_GROUP_FIELDS);

    $named = isoQuoteWithProducts(
        ['iso_group_general' => true, 'iso_group_road_safety' => true, 'iso_group_food' => false, 'iso_g1_notes' => 'Kept'],
        ['Consulenza ISO 9001 + ISO14001 _ 1° Anno', 'Ente_ISO 9001_2 anno', 'Corso 19001'],
    );
    $unnamed = isoQuoteWithProducts(['iso_group_general' => true, 'iso_g1_total_employees' => 5]);
    $unticked = isoQuoteWithProducts(['iso_group_general' => false, 'iso_other_standard' => 'ISO 50001']);
    $untouched = isoQuoteWithProducts(['iso_g1_notes' => 'Alone']);

    test()->seed(IsoRequestedStandardsSeeder::class);
    test()->seed(IsoRequestedStandardsSeeder::class);

    $layout = app(AttributeLayoutService::class)->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All);

    expect($named->fresh()->attribute_values)->toBe(['iso_g1_notes' => 'Kept', IsoCatalogue::STANDARDS => ['standard_9001', 'standard_14001', 'standard_39001']])
        ->and($unnamed->fresh()->attribute_values)->toBe(['iso_g1_total_employees' => 5, IsoCatalogue::STANDARDS => ['standard_9001']])
        ->and($unticked->fresh()->attribute_values)->toBe(['iso_other_standard' => 'ISO 50001'])
        ->and($untouched->fresh()->attribute_values)->toBe(['iso_g1_notes' => 'Alone'])
        ->and(Attribute::query()->whereIn('code', array_keys(IsoRequestedStandardsSeeder::RETIRED_GROUP_FIELDS))->exists())->toBeFalse()
        ->and(Attribute::query()->where('code', IsoCatalogue::STANDARDS)->count())->toBe(1)
        ->and(isoSectionCodes($layout['sections'])['iso-standards'])->toBe([IsoCatalogue::STANDARDS, 'iso_other_standard'])
        // The migrated values keep their block open.
        ->and(app(AttributeSectionVisibility::class)->hiddenCodes($layout, $unnamed->fresh()->attribute_values))->not->toContain('iso_g1_total_employees');
});

it('moves an installation from the list of groups to one checkbox per norm', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    $root = isoCategory(IsoCatalogue::ROOT);
    isoFormBefore0424($root, null);

    $quote = isoQuoteWithProducts(
        [IsoRequestedStandardsSeeder::RETIRED_GROUP_LIST => ['general', 'gender_equality'], 'iso_g6_managers' => 3],
        ['Consulenza ISO 9001 + ISO 14001 + ISO 45001_1° Anno'],
    );

    test()->seed(IsoRequestedStandardsSeeder::class);

    expect($quote->fresh()->attribute_values)->toBe(['iso_g6_managers' => 3, IsoCatalogue::STANDARDS => ['standard_9001', 'standard_14001', 'standard_45001', 'standard_30415']])
        ->and(Attribute::query()->where('code', IsoRequestedStandardsSeeder::RETIRED_GROUP_LIST)->exists())->toBeFalse();
});

it('gives a subcategory the root Commessa fields and an always visible form, and leaves a configured form alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $root = isoCategory(IsoCatalogue::ROOT);
    $category = isoCategory(ISO_SAMPLE_CATEGORY);
    $ownCodes = collect(IsoWorkOrderCatalogue::ATTRIBUTES)->pluck('code')->sort()->values()->all();
    $sections = $layouts->resolveWithFallback($category, AttributeContext::WorkOrder, FormMode::Edit)['sections'];

    expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::WorkOrder)->pluck('code')->sort()->values()->all())
        ->toBe($ownCodes)
        ->and(array_column($sections, 'title'))->toBe(['Dettagli lavorazioni', 'Focus lavorazione ISO', 'Verifiche ispettive'])
        ->and(array_merge(...array_values(isoSectionCodes($sections))))->toEqualCanonicalizing($ownCodes)
        ->and(array_filter(array_column($sections, 'visible_when')))->toBe([])
        ->and(Attribute::query()->where('code', 'iso_inspections')->sole()->config['selectable'])->toBe(['key' => 'active', 'label' => 'Attivo']);

    // A form edited by hand survives the re-run.
    $edited = ['sections' => [array_merge($layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All)['sections'][2], ['sort_order' => 0])]];
    $layouts->upsert($root, AttributeContext::WorkOrder, LayoutFormScope::All, $edited);
    $before = $layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All);

    test()->seed(QualificaCatalogSeeder::class);

    expect($layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All))->toBe($before)
        // No code shared between the two contexts: each list seeds its own attributes.
        ->and(Attribute::query()->whereIn('code', array_column([...IsoCatalogue::QUOTE_ATTRIBUTES, ...IsoWorkOrderCatalogue::ATTRIBUTES], 'code'))->count())
        ->toBe(count(IsoCatalogue::QUOTE_ATTRIBUTES) + count(IsoWorkOrderCatalogue::ATTRIBUTES));
});

/**
 * @return array<string, array<string, array<string, mixed>>> category name => product name => its spec
 */
function isoListProducts(): array
{
    return array_merge(IsoProductCatalogue::PRODUCTS, IsoNamedStandardProductCatalogue::PRODUCTS, IsoMultiStandardProductCatalogue::PRODUCTS);
}

function isoProduct(string $category, string $name): Product
{
    return Product::query()->where('category_id', isoCategory($category)->id)->where('name', $name)->sole();
}

it('files the 322 products of the list on their subcategory, every subcategory with at least one, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $products = isoListProducts();
    $seeded = Product::query()
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', IsoSubcategories::TARGET_CATEGORIES)->select('id'))
        ->with('category')
        ->get();

    expect(array_keys($products))->toEqualCanonicalizing(IsoSubcategories::TARGET_CATEGORIES)
        ->and(array_sum(array_map(count(...), $products)))->toBe(322)
        ->and($seeded)->toHaveCount(322)
        ->and($seeded->groupBy('category.name')->map(fn ($group): array => $group->pluck('name')->sort()->values()->all())->sortKeys()->all())
        ->toBe(collect($products)->map(fn (array $names): array => collect(array_keys($names))->sort()->values()->all())->sortKeys()->all());
});

it('carries the list code, price, VAT rate, typology and "Mesi scadenza" of each product', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $consulting = isoProduct('ISO - 9001', 'Consulenza ISO 9001_2° Anno');
    $exempt = isoProduct('ISO - Marcatura Ce', 'Consulenza Marcatura CE _ 1° Anno');
    $priced = isoProduct('ISO - Ecovadis', 'Badge EcoVadis_Committed');
    $body = isoProduct('ISO - Ente 9001', 'ENTE IQI _ISO 9001_1 ANNO');

    expect($consulting->code)->toBe('9001_2')
        ->and((float) $consulting->vatRate->rate)->toBe(22.0)
        ->and($consulting->productTypology->code)->toBe('consultancy')
        ->and($consulting->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD])->toBe(12)
        ->and($exempt->vatRate->name)->toBe('Nessuna')
        ->and((float) $exempt->vatRate->rate)->toBe(0.0)
        ->and((float) $priced->price)->toBe(400.0)
        ->and($body->productTypology->code)->toBe('institution')
        ->and(isoProduct('ISO - Fgas', 'Iscrizione della ditta al registro Nazionale FGAS')->description)->toBe('compresivi di DIRITTI CAMERALI');
});

it('applies the corrections of the list: third-year codes, repeated codes, duplicated titles', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    expect(isoProduct('ISO - Ente 14001', 'ENTE IQI_ISO 14001_3 ANNO')->code)->toBe('IQI_14_3')
        ->and(isoProduct('ISO - 22000+22005', 'Consulenza ISO 22000 +ISO 22005_3 Anno')->code)->toBe('22+22005_3')
        ->and(isoProduct('ISO - 24000', 'Consulenza PAS 24000_ 3°Anno')->code)->toBe('PAS 24000_3')
        ->and(isoProduct('ISO - 45001', 'Consulenza 45001_ 1° Anno')->code)->toBe('45001')
        ->and(isoProduct('ISO - 45001', 'ISO_Transizione alla 45001_1° anno')->code)->toStartWith('PRD-')
        ->and(isoProduct('ISO - 27017+27018', 'Consulenza ISO/IEC 27017 + ISO/IEC 27018_ 3°Anno')->code)->toBe('27017+27018_3')
        ->and(isoProduct('ISO - Certificazione Bio', 'Sistema di gestione per il mantenimento della Certificazione Bio_3° Anno')->code)->toBe('Certificazione Bio_3');
});

it('links the certifying-body products to IQI once its supplier registry exists', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    expect(isoProduct('ISO - Ente 27001', 'ENTE IQI_ISO 27001_1 ANNO')->productSuppliers()->exists())->toBeFalse();

    $iqi = Registry::factory()->supplier()->create(['name' => 'iqi']);
    test()->seed(QualificaCatalogSeeder::class);

    expect(ProductSupplier::query()->where('supplier_id', $iqi->id)->where('is_default', true)->where('cost', 0)->count())->toBe(18);
});
