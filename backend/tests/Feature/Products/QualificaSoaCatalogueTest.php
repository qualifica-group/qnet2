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
use Database\Seeders\QualificaCatalog\SoaCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "SOA" branch (user directive 2026-10-15, "Q-crm SOA" sheet and
// "PRODOTTI SOA" list): a root linked to the "SOA" business function, worked
// like "Consulenza", with its offer and Commessa fields and its 18 products,
// the attestations linked to their certifying body when it exists.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

function soaCategory(string $name): ProductCategory
{
    return ProductCategory::query()->where('name', $name)->sole();
}

/**
 * @param  list<array<string, mixed>>  $sections
 * @return list<list<string>> the attribute codes of each section
 */
function soaSectionCodes(array $sections): array
{
    return array_map(
        static fn (array $section): array => array_merge(...array_map(
            static fn (array $row): array => array_column($row['items'], 'attribute_code'),
            $section['rows'],
        )),
        $sections,
    );
}

it('seeds the SOA root with two selectable subcategories, the SOA function and the Consulenza rules, idempotently', function (): void {
    // The legacy import brings the function, in its own case: adopted, not duplicated.
    $imported = BusinessFunction::factory()->create(['name' => 'soa']);

    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = soaCategory(SoaCatalogue::ROOT);

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($imported->id)
        ->and(BusinessFunction::query()->whereRaw('LOWER(name) = ?', ['soa'])->count())->toBe(1)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->orderBy('name')->pluck('name')->all())
        ->toBe([SoaCatalogue::CONSULTING_CATEGORY, SoaCatalogue::CERTIFYING_BODY_CATEGORY]);

    foreach (SoaCatalogue::TARGET_CATEGORIES as $name) {
        $category = soaCategory($name);

        expect($category->is_selectable)->toBeTrue($name)
            ->and($category->business_function_id)->toBeNull($name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($imported->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->single_quote_per_opportunity)->toBeFalse()
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives both subcategories the root offer fields and form', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $ownCodes = collect(SoaCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();

    expect(Attribute::query()->where('code', 'soa_service')->sole()->options()->count())->toBe(5)
        ->and(Attribute::query()->where('code', 'soa_category')->sole()->options()->count())->toBe(52)
        ->and(Attribute::query()->where('code', 'soa_classification')->sole()->options()->orderBy('sort_order')->pluck('label')->all())
        ->toBe(['I', 'II', 'III', 'III bis', 'IV', 'IV bis', 'V', 'VI', 'VII', 'VIII']);

    foreach (SoaCatalogue::TARGET_CATEGORIES as $name) {
        $category = soaCategory($name);
        $sections = $layouts->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];

        expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())
            ->toBe($ownCodes)
            ->and(array_column($sections, 'title'))->toBe(['Informazioni preliminari SOA'])
            ->and(soaSectionCodes($sections))->toBe([[
                'soa_service', 'soa_category', 'soa_classification', 'soa_notes', 'soa_processing_notes', 'soa_payment_arrangements',
            ]]);
    }
});

it('gives both subcategories the root Commessa fields and form, and leaves a configured form alone', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $layouts = app(AttributeLayoutService::class);
    $root = soaCategory(SoaCatalogue::ROOT);
    $ownCodes = collect(SoaCatalogue::WORK_ORDER_ATTRIBUTES)->pluck('code')->sort()->values()->all();

    foreach (SoaCatalogue::TARGET_CATEGORIES as $name) {
        $category = soaCategory($name);
        $sections = $layouts->resolveWithFallback($category, AttributeContext::WorkOrder, FormMode::Edit)['sections'];

        expect(app(CategoryHierarchy::class)->effectiveAttributes($category, AttributeContext::WorkOrder)->pluck('code')->sort()->values()->all())
            ->toBe($ownCodes)
            ->and(array_column($sections, 'title'))->toBe(['Info lavorazione SOA', 'Pianificazione (da commesse precedenti)'])
            ->and(array_merge(...soaSectionCodes($sections)))->toEqualCanonicalizing($ownCodes)
            ->and(AttributeLayout::query()->where('product_category_id', $category->id)->exists())->toBeFalse($name);
    }

    // A form edited by hand survives the re-run.
    $edited = ['sections' => [array_merge($layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All)['sections'][1], ['sort_order' => 0])]];
    $layouts->upsert($root, AttributeContext::WorkOrder, LayoutFormScope::All, $edited);
    $before = $layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All);

    test()->seed(QualificaCatalogSeeder::class);

    expect($layouts->resolveExact($root, AttributeContext::WorkOrder, LayoutFormScope::All))->toBe($before)
        ->and(Attribute::query()->where('code', 'like', 'soa_%')->count())
        ->toBe(count(SoaCatalogue::QUOTE_ATTRIBUTES) + count(SoaCatalogue::WORK_ORDER_ATTRIBUTES) + count(SoaCatalogue::PRODUCT_ATTRIBUTES));
});

it('gives both subcategories the root product field "Mesi scadenza", valued on every SOA product', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $attribute = Attribute::query()->where('code', 'soa_expiry_months')->sole();
    $months = Product::query()
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', SoaCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get()
        ->mapWithKeys(fn (Product $product): array => [$product->name => $product->attribute_values['soa_expiry_months'] ?? null]);

    expect($attribute->name)->toBe('Mesi scadenza')
        ->and($attribute->type)->toBe('integer')
        ->and(soaCategory(SoaCatalogue::ROOT)->attributes()->wherePivot('context', AttributeContext::Product->value)->pluck('code')->all())
        ->toBe(['soa_expiry_months']);

    foreach (SoaCatalogue::TARGET_CATEGORIES as $name) {
        expect(app(CategoryHierarchy::class)->effectiveAttributes(soaCategory($name), AttributeContext::Product)->pluck('code')->all())
            ->toBe(['soa_expiry_months']);
    }

    expect($months)->toHaveCount(18)
        ->and($months->only(['Consulenza Soa_I anno', 'Consulenza Soa_II anno', 'Consulenza Soa_III anno'])->all())
        ->toBe(['Consulenza Soa_I anno' => 0, 'Consulenza Soa_II anno' => 12, 'Consulenza Soa_III anno' => 24])
        ->and($months->except(['Consulenza Soa_I anno', 'Consulenza Soa_II anno', 'Consulenza Soa_III anno'])->unique()->values()->all())
        ->toBe([0]);
});

it('fills a missing "Mesi scadenza" on the re-run, without overriding one set by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $consulting = Product::query()->where('category_id', soaCategory(SoaCatalogue::CONSULTING_CATEGORY)->id)->get()->keyBy('name');
    $consulting->get('Consulenza Soa_II anno')->forceFill(['attribute_values' => []])->save();
    $consulting->get('Consulenza Soa_III anno')->forceFill(['attribute_values' => ['soa_expiry_months' => 36]])->save();

    test()->seed(QualificaCatalogSeeder::class);

    expect($consulting->get('Consulenza Soa_II anno')->fresh()->attribute_values['soa_expiry_months'] ?? null)->toBe(12)
        ->and($consulting->get('Consulenza Soa_III anno')->fresh()->attribute_values['soa_expiry_months'] ?? null)->toBe(36);
});

it('seeds the 18 SOA products at 0 with 22% VAT, linking each attestation to its existing supplier', function (): void {
    $argenta = Registry::factory()->supplier()->create(['name' => SoaCatalogue::ARGENTA]);
    $soatech = Registry::factory()->supplier()->create(['name' => 'LA SOATECH SPA']); // compared without case
    // A client of the same name is not a supplier: never linked.
    Registry::factory()->create(['name' => SoaCatalogue::DAP]);

    test()->seed(QualificaCatalogSeeder::class);

    $certifying = Product::query()->where('category_id', soaCategory(SoaCatalogue::CERTIFYING_BODY_CATEGORY)->id)->get()->keyBy('name');
    $consulting = Product::query()->where('category_id', soaCategory(SoaCatalogue::CONSULTING_CATEGORY)->id)->get();

    expect($certifying)->toHaveCount(15)
        ->and($consulting->pluck('name')->sort()->values()->all())
        ->toBe(['Consulenza Soa_I anno', 'Consulenza Soa_II anno', 'Consulenza Soa_III anno'])
        ->and($certifying->merge($consulting)->map(fn (Product $product): float => (float) $product->price)->unique()->values()->all())->toBe([0.0])
        ->and($certifying->merge($consulting)->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and($certifying->get('Attestazione Soa_Argenta')->supplier_id)->toBe($argenta->id)
        ->and($certifying->get('Soa_Variazione_Soatech')->supplier_id)->toBe($soatech->id)
        ->and($certifying->get('Soa_Rinnovo_Dap')->supplier_id)->toBeNull()
        ->and($consulting->pluck('supplier_id')->filter()->all())->toBe([]);
});

it('links a supplier imported after the first seed on the re-run, without overriding one set by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $category = soaCategory(SoaCatalogue::CERTIFYING_BODY_CATEGORY);
    $byHand = Registry::factory()->supplier()->create(['name' => 'Altro ente']);
    Product::query()->where('category_id', $category->id)->where('name', 'Soa_Revisione_Dap')->sole()->update(['supplier_id' => $byHand->id]);
    $dap = Registry::factory()->supplier()->create(['name' => SoaCatalogue::DAP]);

    test()->seed(QualificaCatalogSeeder::class);

    $dapProducts = Product::query()->where('category_id', $category->id)->where('name', 'like', '%_Dap')->pluck('supplier_id', 'name');

    expect(Product::query()->where('category_id', $category->id)->count())->toBe(15)
        ->and($dapProducts->get('Soa_Revisione_Dap'))->toBe($byHand->id)
        ->and($dapProducts->except('Soa_Revisione_Dap')->unique()->values()->all())->toBe([$dap->id]);
});
