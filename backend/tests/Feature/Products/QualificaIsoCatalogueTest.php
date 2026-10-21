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
use App\Models\Registry;
use App\RequestManagement\AttributeSectionVisibility;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Database\Seeders\QualificaCatalog\IsoMultiStandardProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoNamedStandardProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoProductCatalogue;
use Database\Seeders\QualificaCatalog\IsoSubcategories;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "ISO" branch (user directive 2026-10-21): a root linked to the "ISO"
// business function, worked like "Consulenza", with the legacy "Informazioni
// Preliminari Iso" as offer fields — one checkbox per norm group opening its
// block (spec 0288) — and the legacy ISO Commessa blocks, always visible.
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

it('gives a subcategory the root offer fields and a form whose group blocks open on their checkbox', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = isoCategory(ISO_SAMPLE_CATEGORY);
    $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];
    $ownCodes = collect(IsoCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();
    $codesBySection = isoSectionCodes($sections);

    expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
        ->toBe($ownCodes)
        ->and(array_merge(...array_values($codesBySection)))->toEqualCanonicalizing($ownCodes)
        ->and(AttributeLayout::query()->where('product_category_id', $category->id)->exists())->toBeFalse()
        ->and(collect($sections)->mapWithKeys(fn (array $section): array => [$section['id'] => $section['visible_when']['attribute_code'] ?? null])->all())
        ->toBe([
            'iso-standards' => null,
            'iso-certification' => null,
            'iso-organisation' => null,
            'iso-group-general' => 'iso_group_general',
            'iso-group-food' => 'iso_group_food',
            'iso-group-energy' => 'iso_group_energy',
            'iso-group-anti-bribery' => 'iso_group_anti_bribery',
            'iso-group-road-safety' => 'iso_group_road_safety',
            'iso-group-gender-equality' => 'iso_group_gender_equality',
            'iso-consulting' => null,
        ])
        ->and($codesBySection['iso-standards'])->toBe([
            'iso_group_general', 'iso_group_food', 'iso_group_energy',
            'iso_group_anti_bribery', 'iso_group_road_safety', 'iso_group_gender_equality',
        ])
        ->and(Attribute::query()->where('code', 'iso_group_general')->sole()->config)->toBe(['display' => 'checkbox'])
        ->and(Attribute::query()->where('code', 'iso_g1_certification_type')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Prima certificazione', 'Estensione di scopo', 'Estensione di sito', 'Ricertificazione', 'Trasferimento di certificato', 'Variazione di certificato']);
});

it('hides a group block, its certification type select included, until its checkbox is ticked', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layout = app(AttributeLayoutService::class)->resolveWithFallback(isoCategory(ISO_SAMPLE_CATEGORY), AttributeContext::Quote, FormMode::Create);
    $visibility = app(AttributeSectionVisibility::class);

    $hidden = $visibility->hiddenCodes($layout, ['iso_group_energy' => true]);

    expect($hidden)->toContain('iso_g1_certification_type', 'iso_g2_certification_type', 'iso_g4_certification_type')
        ->and($hidden)->not->toContain('iso_g3_certification_type', 'iso_g3_electricity_consumption', 'iso_application_scope', 'iso_group_general');
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

    expect(isoProduct('ISO - Ente 27001', 'ENTE IQI_ISO 27001_1 ANNO')->supplier_id)->toBeNull();

    $iqi = Registry::factory()->supplier()->create(['name' => 'iqi']);
    test()->seed(QualificaCatalogSeeder::class);

    expect(Product::query()->where('supplier_id', $iqi->id)->count())->toBe(18);
});
