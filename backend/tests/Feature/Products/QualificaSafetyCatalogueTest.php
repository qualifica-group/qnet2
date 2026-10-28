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
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\SafetyCatalogue;
use Database\Seeders\QualificaCatalog\SafetyProductCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Sicurezza" branch (user directive 2026-10-15, "Sicurezza 81_08" sheet
// and "SICUREZZA 81_09" list): a root linked to the "Sicurezza" business
// function, worked like "Consulenza", one subcategory per "Categoria" of the
// list, its offer fields and its 127 products under the "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return Collection<int, Product>
 */
function safetyProducts(): Collection
{
    return Product::query()
        ->with(['category', 'productTypology', 'vatRate', 'productSuppliers.supplier'])
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', SafetyCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get();
}

it('seeds the Sicurezza root with its five selectable subcategories, the Sicurezza function and the Consulenza rules, idempotently', function (): void {
    // The legacy import brings the function upper-case: adopted, not duplicated.
    $imported = BusinessFunction::factory()->create(['name' => 'SICUREZZA']);

    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', SafetyCatalogue::ROOT)->sole();

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($imported->id)
        ->and(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['sicurezza'])->count())->toBe(1)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->sort()->values()->all())
        ->toBe(collect(SafetyCatalogue::TARGET_CATEGORIES)->sort()->values()->all());

    foreach (SafetyCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();

        expect($category->is_selectable)->toBeTrue($name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($imported->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives every subcategory the root offer fields and form, and no Commessa field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $ownCodes = collect(SafetyCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();

    expect(Attribute::query()->where('code', 'safety_company_report')->sole()->type)->toBe('boolean')
        ->and(Attribute::query()->where('code', 'safety_welding_method')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['Ad elettrodi', 'A filo', 'Entrambe']);

    foreach (SafetyCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();
        $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];

        expect($hierarchy->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())->toBe($ownCodes)
            ->and($hierarchy->effectiveAttributes($category, AttributeContext::WorkOrder)->all())->toBe([])
            ->and(array_column($sections, 'title'))->toBe(['Sicurezza 81/08', 'Patentino saldatori']);
    }
});

it('replaces the two course fields of the first revision with "Numero e tipo di corsi richiesti" and "Documenti richiesti" on an installation already seeded', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $root = ProductCategory::query()->where('name', SafetyCatalogue::ROOT)->sole();
    $seeded = $layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All);

    // The first revision: its two course fields assigned instead of today's, its form stored.
    $root->attributes()->wherePivot('context', AttributeContext::Quote->value)
        ->detach(Attribute::query()->whereIn('code', ['safety_requested_courses', 'safety_required_documents'])->pluck('id')->all());
    foreach (SafetyCatalogue::RETIRED_ATTRIBUTES as $code) {
        $attribute = Attribute::query()->create(['code' => $code, 'name' => $code, 'type' => 'textarea']);
        $root->attributes()->attach($attribute->id, ['context' => AttributeContext::Quote->value, 'is_required' => false, 'sort_order' => 0]);
    }
    // Built the way SeedsAttributeLayouts::layoutSection() wrote it.
    $section = static fn (string $id, string $title, string $description, int $sortOrder, array $rows): array => [
        'id' => $id, 'title' => $title, 'description' => $description, 'variant' => 'default',
        'collapsible' => false, 'default_collapsed' => false, 'columns' => 2, 'sort_order' => $sortOrder,
        'rows' => array_map(static fn (array $codes, int $index): array => [
            'id' => "{$id}-{$index}",
            'items' => array_map(static fn (string $code): array => [
                'attribute_code' => $code, 'width' => count($codes) === 1 ? 'full' : 'half',
            ], $codes),
        ], $rows, array_keys($rows)),
    ];
    AttributeLayout::query()
        ->where(['product_category_id' => $root->id, 'context' => AttributeContext::Quote->value])
        ->update(['layout' => ['sections' => [
            $section('safety-request', 'Sicurezza 81/08', 'Dati dell\'azienda e corsi richiesti.', 0, [
                ['safety_company_report', 'safety_employee_count'],
                ['safety_requested_course_types'],
                ['safety_course_count_per_type'],
                ['safety_shifts_roles_workplaces'],
                ['safety_active_sites_count', 'safety_company_sites_count'],
            ]),
            $section('safety-welder-licences', 'Patentino saldatori', 'Descrizione patentini: tipo di saldatura, spessori e materiale.', 1, [
                ['safety_welder_licences_count'],
                ['safety_welding_method', 'safety_weld_joint'],
                ['safety_weld_thickness', 'safety_weld_material'],
            ]),
        ]]]);

    test()->seed(QualificaCatalogSeeder::class);

    $subcategory = ProductCategory::query()->where('name', SafetyCatalogue::TRAINING_COURSES)->sole();

    expect(app(CategoryHierarchy::class)->effectiveAttributes($subcategory, AttributeContext::Quote)->pluck('code')->all())
        ->toContain('safety_requested_courses', 'safety_required_documents')
        ->not->toContain(...SafetyCatalogue::RETIRED_ATTRIBUTES)
        ->and(Attribute::query()->where('code', 'safety_requested_courses')->sole()->only(['name', 'type']))
        ->toBe(['name' => 'Numero e tipo di corsi richiesti', 'type' => 'text'])
        ->and(Attribute::query()->where('code', 'safety_required_documents')->sole()->only(['name', 'type']))
        ->toBe(['name' => 'Documenti richiesti', 'type' => 'text'])
        ->and($layouts->resolveExact($root, AttributeContext::Quote, LayoutFormScope::All))->toBe($seeded);
});

it('seeds the 127 listed products with their price, 22% VAT and the Consulenza typology, the repeated title split by code', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $products = safetyProducts();
    $perCategory = $products->countBy('category.name')->sortKeys()->all();

    expect($products)->toHaveCount(127)
        ->and($perCategory)->toBe([
            SafetyCatalogue::TRAINING_COURSES => 86,
            SafetyCatalogue::DOCUMENTS => 29,
            SafetyCatalogue::SITE_INSPECTIONS => 5,
            SafetyCatalogue::TRAVEL => 1,
            SafetyCatalogue::MEDICAL_VISITS => 6,
        ])
        ->and($products->pluck('productTypology.code')->unique()->values()->all())->toBe(['consultancy'])
        ->and($products->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and((float) $products->firstWhere('name', 'Corso di Formazione Patentino Fgas (Compreso di codice PR)')->price)->toBe(1200.0)
        ->and($products->filter(fn (Product $product): bool => str_contains($product->name, 'durata 16 ore ('))->mapWithKeys(fn (Product $product): array => [$product->name => (float) $product->price])->sortKeys()->all())
        ->toBe([
            "Formazione dei datori di lavoro ai sensi dell'Accordo Stato Regioni del 17/04/2025 - durata 16 ore (DL)" => 150.0,
            "Formazione dei datori di lavoro ai sensi dell'Accordo Stato Regioni del 17/04/2025 - durata 16 ore (RSPP/DAT.)" => 120.0,
        ]);
});

it('links the F-gas products to GONDOLA SRLS and the welding ones to ND CONTROL, when those suppliers exist', function (): void {
    $gondola = Registry::factory()->supplier()->create(['name' => 'Gondola Srls']); // compared without case

    test()->seed(QualificaCatalogSeeder::class);

    $bySupplier = safetyProducts()->filter(fn (Product $product): bool => $product->productSuppliers->isNotEmpty())
        ->groupBy(fn (Product $product): string => $product->productSuppliers->sole()->supplier->name)
        ->map(fn (Collection $products): array => $products->pluck('name')->sort()->values()->all());

    expect($bySupplier->all())->toBe([
        'Gondola Srls' => ['Corso di Formazione Patentino Fgas (Compreso di codice PR)', 'Iscrizione Portale F-gas e codice IR'],
    ]);

    // ND CONTROL arrives later: the re-run links its four welding products.
    $ndControl = Registry::factory()->supplier()->create(['name' => SafetyCatalogue::ND_CONTROL]);
    test()->seed(QualificaCatalogSeeder::class);

    expect(safetyProducts()->filter(fn (Product $product): bool => $product->productSuppliers->contains('supplier_id', $ndControl->id))->pluck('name')->sort()->values()->all())->toBe([
        'Patentino Saldatori',
        'Prove iniziali di tipo ITT',
        'Qualifica dei processi di saldatura WPQR /WPS',
        'Qualifica dei processi di saldatura WPQR | Welding Procedure Qualification Record',
    ])
        ->and(safetyProducts()->filter(fn (Product $product): bool => $product->productSuppliers->contains('supplier_id', $gondola->id)))->toHaveCount(2);
});

it('writes the listed "Mesi scadenza" in the products custom field, leaving blank the products the list leaves blank', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $months = safetyProducts()->mapWithKeys(fn (Product $product): array => [
        $product->name => $product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null,
    ]);
    $blank = collect(SafetyProductCatalogue::PRODUCTS)->flatMap(fn (array $products): array => $products)
        ->filter(fn (array $spec): bool => $spec['expiration_months'] === null)->keys()->sort()->values()->all();

    expect($blank)->toBe(['Aula di formazione Primo Soccorso', 'Aula di formazione antincendio', 'Aula di formazione generale e specifica', 'Aula di formazione per preposto'])
        ->and($months->only($blank)->unique()->values()->all())->toBe([null])
        ->and($months->get('Intregrazione esami ematici- visite mediche'))->toBe(20)
        ->and($months->except([...$blank, 'Intregrazione esami ematici- visite mediche'])->unique()->values()->all())->toBe([0]);
});
