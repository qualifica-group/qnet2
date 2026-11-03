<?php

use App\Enums\CategoryManagementMode;
use App\Models\BusinessFunction;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\VatRate;
use App\Services\ProductCategories\CategoryHierarchy;
use Database\Seeders\ProductTypologySeeder;
use Database\Seeders\QualificaCatalog\ProjectDesignCatalogue;
use Database\Seeders\QualificaCatalog\ProjectDesignProductCatalogue;
use Database\Seeders\QualificaCatalogSeeder;
use Database\Seeders\QualificaProjectDesignCatalogSeeder;
use Database\Seeders\QualificaTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The "Progettazione" branch (user directive 2026-11-03, "Funzione Aziendale_
// Progettazione" list): a root linked to the "Progettazione" business
// function, worked like "Consulenza", with the single line "Progettazione
// interna" and its eleven products, every one at 0 and "Esente IVA".
// Also seeded on its own, for the live installation, by
// QualificaProjectDesignCatalogSeeder.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Keeps the catalogue step from offering the q-crm import.
    config(['migrations.base_url' => null]);
});

function expectProjectDesignBranch(): void
{
    $root = ProductCategory::query()->where('name', ProjectDesignCatalogue::ROOT)->sole();
    $function = BusinessFunction::query()->where('name', ProjectDesignCatalogue::BUSINESS_FUNCTION)->sole();
    $hierarchy = app(CategoryHierarchy::class);

    expect($root->parent_id)->toBeNull()
        ->and($root->is_selectable)->toBeFalse()
        ->and($root->business_function_id)->toBe($function->id);

    foreach (ProjectDesignProductCatalogue::PRODUCTS as $categoryName => $products) {
        $line = ProductCategory::query()->where('name', $categoryName)->sole();

        expect($line->parent_id)->toBe($root->id)
            ->and($line->is_selectable)->toBeTrue()
            ->and($hierarchy->effectiveBusinessFunction($line)['id'] ?? null)->toBe($function->id)
            ->and($line->management_mode)->toBe(CategoryManagementMode::Multiple)
            ->and($line->generates_contract)->toBeTrue()
            ->and($line->simplified_offer_line)->toBeFalse();

        $seeded = Product::query()->with(['productTypology', 'vatRate'])->where('category_id', $line->id)->get();

        expect($seeded->pluck('name')->sort()->values()->all())->toBe(collect(array_keys($products))->sort()->values()->all());

        foreach ($seeded as $product) {
            expect((float) $product->price)->toBe(0.0)
                ->and($product->code)->toStartWith('PRD-')
                ->and($product->productTypology->code)->toBe('consultancy')
                ->and($product->vatRate->name)->toBe('Esente IVA')
                ->and((float) $product->vatRate->rate)->toBe(0.0);
        }
    }
}

it('seeds the Progettazione branch with the full catalogue, idempotently', function (): void {
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);
    test()->seed(QualificaCatalogSeeder::class);

    expectProjectDesignBranch();
    expect(VatRate::query()->where('name', 'Esente IVA')->count())->toBe(1);
});

it('adds the branch to a live installation without touching the rest, idempotently', function (): void {
    // Step 1: the state of a live installation seeded before the branch existed.
    test()->seed(QualificaTemplateSeeder::class);
    test()->seed(ProductTypologySeeder::class);
    BusinessFunction::query()->create(['name' => ProjectDesignCatalogue::BUSINESS_FUNCTION]);
    $otherRoot = ProductCategory::factory()->create([
        'name' => 'Consulenza',
        'parent_id' => null,
        'management_mode' => CategoryManagementMode::Single,
    ]);

    // Step 2: the standalone seeder, twice.
    test()->seed(QualificaProjectDesignCatalogSeeder::class);
    $editedProduct = Product::query()->where('name', 'Progettazione PA')->sole();
    $editedProduct->update(['price' => 150]);
    test()->seed(QualificaProjectDesignCatalogSeeder::class);

    // Step 3: the branch is there once, the edit survives, the other root is untouched.
    expect(Product::query()->count())->toBe(array_sum(array_map(count(...), ProjectDesignProductCatalogue::PRODUCTS)))
        ->and(ProductCategory::query()->count())->toBe(3)
        ->and((float) $editedProduct->fresh()->price)->toBe(150.0)
        ->and($otherRoot->fresh()->management_mode)->toBe(CategoryManagementMode::Single);

    $editedProduct->update(['price' => 0]);
    expectProjectDesignBranch();
});

it('stops and writes nothing when a branch category already sits elsewhere in the tree', function (): void {
    test()->seed(ProductTypologySeeder::class);
    $parent = ProductCategory::factory()->create(['name' => 'Old category', 'parent_id' => null]);
    ProductCategory::factory()->create(['name' => ProjectDesignCatalogue::INTERNAL, 'parent_id' => $parent->id]);

    expect(fn () => test()->seed(QualificaProjectDesignCatalogSeeder::class))
        ->toThrow(RuntimeException::class, 'Category "Progettazione interna" already exists elsewhere in the catalogue');

    expect(ProductCategory::query()->where('name', ProjectDesignCatalogue::ROOT)->exists())->toBeFalse()
        ->and(Product::query()->count())->toBe(0);
});
