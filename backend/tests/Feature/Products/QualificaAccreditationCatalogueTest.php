<?php

use App\Enums\AttributeContext;
use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\QualificaCatalog\AccreditationCatalogue;
use Database\Seeders\QualificaCatalog\BranchProducts;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Accreditamenti" branch (user directive 2026-10-20,
// "ACCREDITAMENTI_PARTNERSHIP SEDI" list): a root linked to the
// "Accreditamenti" business function, worked like "Consulenza", its single
// product line with no offer field, and the one listed product under the
// "Consulenza" typology.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

it('seeds the Accreditamenti root, its selectable line and its product, with no offer field, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    $root = ProductCategory::query()->where('name', AccreditationCatalogue::ROOT)->sole();
    $line = ProductCategory::query()->where('name', AccreditationCatalogue::BRANCH_PARTNERSHIPS)->sole();
    $function = BusinessFunction::query()->where('name', AccreditationCatalogue::BUSINESS_FUNCTION)->sole();
    $hierarchy = app(CategoryHierarchy::class);

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id)
        ->and($line->parent_id)->toBe($root->id)
        ->and($line->is_selectable)->toBeTrue()
        ->and($hierarchy->effectiveBusinessFunction($line)['id'] ?? null)->toBe($function->id)
        ->and($line->management_mode)->toBe(CategoryManagementMode::Multiple)
        ->and($line->generates_contract)->toBeTrue()
        ->and($line->simplified_offer_line)->toBeFalse()
        ->and($hierarchy->effectiveAttributes($line, AttributeContext::Quote)->all())->toBe([])
        ->and($hierarchy->effectiveAttributes($line, AttributeContext::WorkOrder)->all())->toBe([]);

    $product = Product::query()->with(['productTypology', 'vatRate'])->where('category_id', $line->id)->sole();

    expect($product->name)->toBe('partnerships-sedi')
        // The list's "Codice" (user directive 2026-10-20).
        ->and($product->code)->toBe('GOL')
        ->and((float) $product->price)->toBe(0.0)
        ->and($product->productTypology->code)->toBe('consultancy')
        ->and((string) $product->vatRate?->rate)->toBe('22.00')
        ->and($product->custom_fields[BranchProducts::EXPIRATION_MONTHS_FIELD] ?? null)->toBe(0);
});

it('fails the seed when the list\'s code is already used by another product', function (): void {
    Product::factory()->create(['code' => 'GOL']);

    expect(fn () => test()->seed(QualificaCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'Product code "GOL" of "partnerships-sedi" is already used by another product.');
});
