<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Models\Attribute;
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
        ->with(['category', 'productTypology', 'vatRate', 'supplier'])
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

    $bySupplier = safetyProducts()->filter(fn (Product $product): bool => $product->supplier_id !== null)
        ->groupBy('supplier.name')
        ->map(fn (Collection $products): array => $products->pluck('name')->sort()->values()->all());

    expect($bySupplier->all())->toBe([
        'Gondola Srls' => ['Corso di Formazione Patentino Fgas (Compreso di codice PR)', 'Iscrizione Portale F-gas e codice IR'],
    ]);

    // ND CONTROL arrives later: the re-run links its four welding products.
    $ndControl = Registry::factory()->supplier()->create(['name' => SafetyCatalogue::ND_CONTROL]);
    test()->seed(QualificaCatalogSeeder::class);

    expect(safetyProducts()->where('supplier_id', $ndControl->id)->pluck('name')->sort()->values()->all())->toBe([
        'Patentino Saldatori',
        'Prove iniziali di tipo ITT',
        'Qualifica dei processi di saldatura WPQR /WPS',
        'Qualifica dei processi di saldatura WPQR | Welding Procedure Qualification Record',
    ])
        ->and(safetyProducts()->where('supplier_id', $gondola->id))->toHaveCount(2);
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
