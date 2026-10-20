<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalog\EducationCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Istruzione" branch (user directive 2026-10-20, "ISTRUZIONE" list): a
// root linked to the "Istruzione" business function, worked like
// "Consulenza", its four product lines with no offer field, and the 18 listed
// products under the "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return Collection<int, Product>
 */
function educationProducts(): Collection
{
    return Product::query()
        ->with(['category', 'productTypology', 'vatRate'])
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', EducationCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get();
}

it('seeds the Istruzione root with its four selectable lines, the Istruzione function, the Consulenza rules and no offer field, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', EducationCatalogue::ROOT)->sole();
    $function = BusinessFunction::query()->where('name', EducationCatalogue::BUSINESS_FUNCTION)->sole();
    $hierarchy = app(CategoryHierarchy::class);

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['Istruzione - ISTRUZIONE', 'Istruzione - PNRR', 'Istruzione - Consulenza Scuola', 'Istruzione - Conto Termico 3.0']);

    foreach (EducationCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();

        expect($category->is_selectable)->toBeTrue($name)
            ->and($hierarchy->effectiveBusinessFunction($category)['id'] ?? null)->toBe($function->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse()
            ->and($hierarchy->effectiveAttributes($category, AttributeContext::Quote)->all())->toBe([])
            ->and($hierarchy->effectiveAttributes($category, AttributeContext::WorkOrder)->all())->toBe([]);
    }
});

it('seeds the 18 listed products with description, 22% VAT, the Consulenza typology and "Mesi scadenza", idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $products = educationProducts()->keyBy('name');

    expect($products)->toHaveCount(18)
        ->and($products->countBy('category.name')->all())->toEqual([
            EducationCatalogue::EDUCATION => 1,
            EducationCatalogue::PNRR => 14,
            EducationCatalogue::SCHOOL_CONSULTING => 2,
            EducationCatalogue::THERMAL_ACCOUNT => 1,
        ])
        ->and($products->pluck('productTypology.code')->unique()->values()->all())->toBe(['consultancy'])
        ->and($products->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and($products->map(fn (Product $product): float => (float) $product->price)->unique()->values()->all())->toBe([0.0])
        ->and($products->map(fn (Product $product): mixed => $product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null)->unique()->values()->all())->toBe([0])
        ->and($products->get('DM88')->description)->toBe('ALTERNANZA SCUOLA LAVORO')
        ->and($products->get('CONTO TERMICO 3.0')->description)->toStartWith('Gli incentivi sono determinati')
        ->toContain("unità di superficie\nunità di potenza")
        ->toEndWith('prodotti nell\'Unione Europea.')
        ->and($products->get('Corso Digital Lab')->description)->toBeNull();
});
