<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Enums\FormMode;
use App\Models\Attribute;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutService;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\GdprCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "GDPR" branch (user directive 2026-10-20, "gestione richieste gdpr"
// sheet and "SERVIZI GDPR" list): a root linked to the "Privacy" business
// function, worked like "Consulenza", the two product lines, the sheet's offer
// fields on the root and the 16 listed products (the yellow row left out)
// under the "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return Collection<int, Product>
 */
function gdprProducts(): Collection
{
    return Product::query()
        ->with(['category', 'productTypology', 'vatRate'])
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', GdprCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get();
}

it('seeds the GDPR root with its two selectable lines, the Privacy function and the Consulenza rules, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', GdprCatalogue::ROOT)->sole();
    $function = BusinessFunction::query()->where('name', GdprCatalogue::BUSINESS_FUNCTION)->sole();

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['GDPR - GDPR', 'GDPR - Nomina DPO']);

    foreach (GdprCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();

        expect($category->is_selectable)->toBeTrue($name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($function->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives both lines the root offer fields and form, and no Commessa field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $ownCodes = collect(GdprCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();

    expect(Attribute::query()->whereIn('code', $ownCodes)->pluck('type', 'code')->all())->toEqual([
        'gdpr_employee_count' => 'integer',
        'gdpr_business_activity' => 'textarea',
        'gdpr_video_surveillance' => 'boolean',
        'gdpr_video_surveillance_sites' => 'text',
        'gdpr_checklist' => 'boolean',
        'gdpr_notes' => 'textarea',
    ]);

    foreach (GdprCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();
        $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];

        expect($hierarchy->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())->toBe($ownCodes)
            ->and($hierarchy->effectiveAttributes($category, AttributeContext::WorkOrder)->all())->toBe([])
            ->and(array_column($sections, 'title'))->toBe(['GDPR']);
    }
});

it('seeds the 16 listed products, not the yellow one, with price, description, 22% VAT, the Consulenza typology and "Mesi scadenza", idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $products = gdprProducts()->keyBy('name');
    $months = $products->map(fn (Product $product): mixed => $product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null);

    expect($products)->toHaveCount(16)
        ->and($products->countBy('category.name')->all())->toEqual([GdprCatalogue::GDPR => 7, GdprCatalogue::DPO_APPOINTMENT => 9])
        ->and(Product::query()->where('name', 'GDPR_')->exists())->toBeFalse()
        ->and($products->pluck('productTypology.code')->unique()->values()->all())->toBe(['consultancy'])
        ->and($products->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and((float) $products->get('Attività di Verifica e Mantenimento annuo adeguamento GDPR 2016/679 2° anno')->price)->toBe(500.0)
        ->and($products->get('DPO_2° anno')->description)->toBe('Revisione documentazione + Analisi ed interventi sui processi aziendali + Formazione del Personale')
        ->and($products->get('Canone cloud_1° anno')->description)->toBeNull()
        ->and([$months->get('Canone cloud_1° anno'), $months->get('Canone cloud_2° anno'), $months->get('Canone cloud_3° anno')])->toBe([0, 12, 24]);
});

it('fills a blank description on a re-run and keeps one typed by hand', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $products = gdprProducts()->keyBy('name');
    $products->get('DPO_1° anno')->update(['description' => null]);
    $products->get('DPO_3° anno')->update(['description' => 'Scritta a mano']);

    test()->seed(QualificaCatalogSeeder::class);

    $products = gdprProducts()->keyBy('name');

    expect($products->get('DPO_1° anno')->description)->toBe('Predisposizione documentazione + Analisi ed interventi sui processi aziendali + Formazione del personale')
        ->and($products->get('DPO_3° anno')->description)->toBe('Scritta a mano');
});
