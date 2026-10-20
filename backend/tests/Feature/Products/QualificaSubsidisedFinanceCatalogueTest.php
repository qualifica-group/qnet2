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
use Database\Seeders\QualificaCatalog\SubsidisedFinanceCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Finanza Agevolata" branch (user directive 2026-10-20, "Gestione
// richieste finanza Agevolata" sheet and "FINANZA AGEVOLATA" list): a root
// linked to the "Finanza Agevolata" business function, worked like
// "Consulenza", the thirteen product lines, the sheet's offer fields on the
// root and the 47 listed products under the "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

/**
 * @return Collection<int, Product>
 */
function subsidisedFinanceProducts(): Collection
{
    return Product::query()
        ->with(['category', 'productTypology', 'vatRate'])
        ->whereIn('category_id', ProductCategory::query()->whereIn('name', SubsidisedFinanceCatalogue::TARGET_CATEGORIES)->select('id'))
        ->get();
}

it('seeds the Finanza Agevolata root with its thirteen selectable lines, its function and the Consulenza rules, idempotently', function (): void {
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', SubsidisedFinanceCatalogue::ROOT)->sole();
    $function = BusinessFunction::query()->where('name', SubsidisedFinanceCatalogue::BUSINESS_FUNCTION)->sole();

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id)
        ->and(ProductCategory::query()->where('parent_id', $root->id)->pluck('name')->all())
        ->toEqualCanonicalizing(array_map(fn (string $line): string => "Finanza Agevolata - {$line}", [
            'AUT C.NORD', 'Decreto Turismo', 'FINANZA AGEVOLATA', 'FNC', 'FONDI', 'FORMAZIONE CONTINUA', 'FRC',
            'FUNT', 'INCENTIVI ASSUNZIONI', 'Industria 4.0', 'IPERAMMORTAMENTO', 'ISI INAIL', 'RESTO AL SUD',
        ]));

    foreach (SubsidisedFinanceCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();

        expect($category->is_selectable)->toBeTrue($name)
            ->and(app(CategoryHierarchy::class)->effectiveBusinessFunction($category)['id'] ?? null)->toBe($function->id)
            ->and($category->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($category->generates_contract)->toBeTrue()
            ->and($category->simplified_offer_line)->toBeFalse();
    }
});

it('gives every line the root offer fields and form, and no Commessa field', function (): void {
    test()->seed(QualificaCatalogSeeder::class);

    $hierarchy = app(CategoryHierarchy::class);
    $ownCodes = collect(SubsidisedFinanceCatalogue::QUOTE_ATTRIBUTES)->pluck('code')->sort()->values()->all();

    expect(Attribute::query()->whereIn('code', $ownCodes)->pluck('type', 'code')->all())->toEqual([
        'subsidised_finance_requested_measure' => 'text',
        'subsidised_finance_checklist' => 'boolean',
        'subsidised_finance_notes' => 'textarea',
    ]);

    foreach (SubsidisedFinanceCatalogue::TARGET_CATEGORIES as $name) {
        $category = ProductCategory::query()->where('name', $name)->sole();
        $sections = app(AttributeLayoutService::class)->resolveWithFallback($category, AttributeContext::Quote, FormMode::Create)['sections'];

        expect($hierarchy->effectiveAttributes($category, AttributeContext::Quote)->pluck('code')->sort()->values()->all())->toBe($ownCodes)
            ->and($hierarchy->effectiveAttributes($category, AttributeContext::WorkOrder)->all())->toBe([])
            ->and(array_column($sections, 'title'))->toBe(['Finanza Agevolata']);
    }
});

it('seeds the 47 listed products with price, description, 22% VAT, the Consulenza typology and "Mesi scadenza" 0, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $products = subsidisedFinanceProducts()->keyBy('name');

    expect($products)->toHaveCount(47)
        ->and($products->countBy('category.name')->all())->toEqual([
            SubsidisedFinanceCatalogue::SELF_EMPLOYMENT_CENTRE_NORTH => 2,
            SubsidisedFinanceCatalogue::TOURISM_DECREE => 1,
            SubsidisedFinanceCatalogue::SUBSIDISED_FINANCE => 11,
            SubsidisedFinanceCatalogue::FNC => 6,
            SubsidisedFinanceCatalogue::FUNDS => 3,
            SubsidisedFinanceCatalogue::CONTINUING_TRAINING => 2,
            SubsidisedFinanceCatalogue::FRC => 3,
            SubsidisedFinanceCatalogue::FUNT => 2,
            SubsidisedFinanceCatalogue::HIRING_INCENTIVES => 2,
            SubsidisedFinanceCatalogue::INDUSTRY_4_0 => 4,
            SubsidisedFinanceCatalogue::HYPER_DEPRECIATION => 1,
            SubsidisedFinanceCatalogue::ISI_INAIL => 7,
            SubsidisedFinanceCatalogue::RESTO_AL_SUD => 3,
        ])
        ->and($products->pluck('productTypology.code')->unique()->values()->all())->toBe(['consultancy'])
        ->and($products->map(fn (Product $product): string => (string) $product->vatRate?->rate)->unique()->values()->all())->toBe(['22.00'])
        ->and((float) $products->get('Vendita ed installazione Software Qnet e relativa personalizzazione per business cliente')->price)->toBe(12000.0)
        ->and($products->get('Consulenza ISI Inail - IV fase')->description)->toBe('8% calcolato sul contributo a fondo perduto.')
        ->and($products->map(fn (Product $product): mixed => $product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null)->unique()->values()->all())->toBe([0]);
});
