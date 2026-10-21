<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Enums\LayoutFormScope;
use App\Models\Attribute;
use App\Models\AttributeLayout;
use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\RequestManagement\AttributeSectionVisibility;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\IsoCatalogue;
use Database\Seeders\QualificaCatalog\IsoWorkOrderCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "ISO" branch (user directive 2026-10-21): a root linked to the "ISO"
// business function, worked like "Consulenza", with the legacy "Informazioni
// Preliminari Iso" as offer fields — one checkbox per norm group opening its
// block (spec 0288) — and the legacy ISO Commessa blocks, always visible.
uses(RefreshDatabase::class);

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

it('seeds the ISO root with one selectable subcategory, the ISO function and the Consulenza rules, idempotently', function (): void {
    // The legacy import brings the function, in its own case: adopted, not duplicated.
    $imported = BusinessFunction::factory()->create(['name' => 'iso']);

    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = isoCategory(IsoCatalogue::ROOT);
    $category = isoCategory(IsoCatalogue::CERTIFICATION_CATEGORY);

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($imported->id)
        ->and(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['iso'])->count())->toBe(1)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->all())->toBe([IsoCatalogue::CERTIFICATION_CATEGORY])
        ->and($category->is_selectable)->toBeTrue()
        ->and($category->business_function_id)->toBeNull()
        ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($imported->id)
        ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
        ->and($category->single_quote_per_opportunity)->toBeFalse()
        ->and($category->generates_contract)->toBeTrue()
        ->and($category->simplified_offer_line)->toBeFalse();
});

it('gives the subcategory the root offer fields and a form whose group blocks open on their checkbox', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = isoCategory(IsoCatalogue::CERTIFICATION_CATEGORY);
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

    $layout = app(AttributeLayoutService::class)->resolveWithFallback(isoCategory(IsoCatalogue::CERTIFICATION_CATEGORY), AttributeContext::Quote, FormMode::Create);
    $visibility = app(AttributeSectionVisibility::class);

    $hidden = $visibility->hiddenCodes($layout, ['iso_group_energy' => true]);

    expect($hidden)->toContain('iso_g1_certification_type', 'iso_g2_certification_type', 'iso_g4_certification_type')
        ->and($hidden)->not->toContain('iso_g3_certification_type', 'iso_g3_electricity_consumption', 'iso_application_scope', 'iso_group_general');
});

it('gives the subcategory the root Commessa fields and an always visible form, and leaves a configured form alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $root = isoCategory(IsoCatalogue::ROOT);
    $category = isoCategory(IsoCatalogue::CERTIFICATION_CATEGORY);
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
