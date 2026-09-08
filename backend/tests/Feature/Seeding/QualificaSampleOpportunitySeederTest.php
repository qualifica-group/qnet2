<?php

use App\Enums\CategoryManagementMode;
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
//
// User directive 2026-08-31: an anagrafica carries ONE open opportunity at a
// time, so the batch is capped by how many FREE anagrafiche exist — every
// fixture that expects the full batch seeds SAMPLE_OPPORTUNITIES of them.
uses(RefreshDatabase::class);

/** The seeder's own default batch size (QualificaSampleOpportunitySeeder::DEFAULT_OPPORTUNITIES). */
const SAMPLE_OPPORTUNITIES = 10;

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
    Registry::factory()->count(SAMPLE_OPPORTUNITIES)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    $opportunities = Opportunity::query()->with(['productLines', 'productsOfInterest'])->get();

    expect($opportunities)->toHaveCount(SAMPLE_OPPORTUNITIES)
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
    Registry::factory()->count(SAMPLE_OPPORTUNITIES)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    $lineCategoryIds = OpportunityProductLine::query()->pluck('product_category_id')->unique()->values()->all();

    expect(Opportunity::query()->count())->toBe(SAMPLE_OPPORTUNITIES)
        ->and($lineCategoryIds)->toBe([$target->getKey()]);
});

it('gives a single-mode card exactly one product line (spec 0077 INV-3)', function (): void {
    // Two sibling targets under a `single` root, both carrying a product:
    // the draw must still stop at one line per deal, as the form does.
    $root = ProductCategory::factory()->create([
        'business_function_id' => BusinessFunction::factory(),
        'is_selectable' => false,
        'management_mode' => CategoryManagementMode::Single,
    ]);

    foreach (['first', 'second'] as $ignored) {
        $target = ProductCategory::factory()->childOf($root)->create([
            'is_selectable' => true,
            'management_mode' => CategoryManagementMode::Single,
        ]);
        Product::factory()->create(['category_id' => $target->getKey()]);
    }

    Registry::factory()->count(SAMPLE_OPPORTUNITIES)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);

    $lineCounts = Opportunity::query()->withCount('productLines')->pluck('product_lines_count')->unique()->values()->all();

    expect(Opportunity::query()->count())->toBe(SAMPLE_OPPORTUNITIES)
        ->and($lineCounts)->toBe([1]);
});

it('appends a second batch on re-run, on the anagrafiche still free', function () use ($seedOffer): void {
    // User directive 2026-09-08: the seeder ACCUMULATES. What caps a run is
    // the pool of free anagrafiche, never a guard — so a pool twice the batch
    // size yields two full batches.
    $seedOffer();
    Registry::factory()->count(SAMPLE_OPPORTUNITIES * 2)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);
    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(SAMPLE_OPPORTUNITIES * 2);
});

it('sizes its batch from the run() argument, so one run can be made bigger', function () use ($seedOffer): void {
    // `--opportunities` of qualifica:seed-sample lands here — the alternative
    // to launching the chain several times.
    $seedOffer();
    Registry::factory()->count(SAMPLE_OPPORTUNITIES)->create();

    app(QualificaSampleOpportunitySeeder::class)->run(opportunities: 3);

    expect(Opportunity::query()->count())->toBe(3);
});

it('seeds nothing more once every anagrafica carries an open deal', function () use ($seedOffer): void {
    // The pool IS the cap: an anagrafica carries ONE open opportunity at a
    // time (user directive 2026-08-31), so a second run over an exhausted
    // pool is an empty batch, not a duplicated one.
    $seedOffer();
    Registry::factory()->count(SAMPLE_OPPORTUNITIES)->create();

    test()->seed(QualificaSampleOpportunitySeeder::class);
    test()->seed(QualificaSampleOpportunitySeeder::class);

    expect(Opportunity::query()->count())->toBe(SAMPLE_OPPORTUNITIES);
});
