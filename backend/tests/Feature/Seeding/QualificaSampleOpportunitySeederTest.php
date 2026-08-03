<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Registry;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The standalone half of the sample pipeline: deals created with no lead
// behind them, on the Anagrafiche the lead step already seeded.
uses(RefreshDatabase::class);

$seedOffer = function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey()]);
};

it('skips itself when there is no Anagrafica to hang a deal on', function () use ($seedOffer): void {
    $seedOffer();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('skips itself when no category pairs a business function with a product', function (): void {
    // A category with a business function but no product fills
    // `product_lines` and leaves `products_of_interest` empty — a row the
    // opportunity form itself would refuse.
    ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Registry::factory()->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('seeds deals with no lead, both mandatory collections filled', function () use ($seedOffer): void {
    $seedOffer();
    Registry::factory()->count(3)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    $opportunities = Opportunity::query()->with(['productLines', 'productsOfInterest'])->get();

    expect($opportunities)->toHaveCount(10)
        ->and($opportunities->whereNotNull('lead_id'))->toBeEmpty()
        ->and($opportunities->filter(fn (Opportunity $deal): bool => $deal->productLines->isEmpty()))->toBeEmpty()
        ->and($opportunities->filter(fn (Opportunity $deal): bool => $deal->productsOfInterest->isEmpty()))->toBeEmpty();
});

it('skips itself when the only category carrying a product is a container', function (): void {
    // Spec 0074: an unselectable category is not a classification target, so
    // it cannot become a product line — nor can the product filed on it be
    // drawn, since the coverage rule would add that very line back.
    $container = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory(),
        'is_selectable' => false,
    ]);
    Product::factory()->create(['category_id' => $container->getKey()]);
    Registry::factory()->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(0);
});

it('never lines a deal up with an unselectable category', function (): void {
    $container = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory(),
        'is_selectable' => false,
    ]);
    $target = ProductCategory::factory()->childOf($container)->create(['is_selectable' => true]);
    // One product on the container, one on the selectable leaf: only the
    // latter may reach a deal.
    Product::factory()->create(['category_id' => $container->getKey()]);
    Product::factory()->create(['category_id' => $target->getKey()]);
    Registry::factory()->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    $lineCategoryIds = OpportunityProductLine::query()->pluck('product_category_id')->unique()->values()->all();

    expect(Opportunity::query()->count())->toBe(10)
        ->and($lineCategoryIds)->toBe([$target->getKey()]);
});

it('is idempotent: a second run adds nothing', function () use ($seedOffer): void {
    $seedOffer();
    Registry::factory()->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);
    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(10);
});
