<?php

use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductCategory;
use Database\Seeders\DemoCatalog\DemoCostProductCatalogue;
use Database\Seeders\DemoCostProductSeeder;
use Database\Seeders\UnitOfMeasureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The demo cost-only products (spec 0142): cars, train tickets, hotels...
// usable only on an Offerta's Costi tab.
uses(RefreshDatabase::class);

it('seeds every catalogue item as a cost-only product under its own leaf', function (): void {
    // The units come from the clean seed (DatabaseSeeder), which always runs first.
    test()->seed(UnitOfMeasureSeeder::class);
    test()->seed(DemoCostProductSeeder::class);

    $root = ProductCategory::query()->where('name', DemoCostProductCatalogue::ROOT)->whereNull('parent_id')->sole();

    foreach (DemoCostProductCatalogue::PRODUCTS as $categoryName => $items) {
        $leaf = ProductCategory::query()->where('name', $categoryName)->where('parent_id', $root->id)->sole();

        foreach ($items as $item) {
            $product = Product::query()->with('unitOfMeasure')->where('name', $item['name'])->where('category_id', $leaf->id)->sole();

            expect($product->usages->all())->toBe([ProductUsage::Cost])
                ->and((float) $product->cost)->toBe($item['cost'])
                ->and($product->unitOfMeasure->code)->toBe($item['unit']);
        }
    }
});

it('leaves the branch without a business function, so it never pairs into a product line', function (): void {
    test()->seed(DemoCostProductSeeder::class);

    expect(ProductCategory::query()->where('name', DemoCostProductCatalogue::ROOT)->value('business_function_id'))->toBeNull();
});

it('is idempotent: a re-run duplicates neither categories nor products', function (): void {
    test()->seed(DemoCostProductSeeder::class);
    $categories = ProductCategory::query()->count();
    $products = Product::query()->count();

    test()->seed(DemoCostProductSeeder::class);

    expect(ProductCategory::query()->count())->toBe($categories)
        ->and(Product::query()->count())->toBe($products)
        ->and($products)->toBe(array_sum(array_map(count(...), DemoCostProductCatalogue::PRODUCTS)));
});
