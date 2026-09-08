<?php

use App\Models\BusinessFunction;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\QualificaSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The single entry point for the FABRICATED pipeline, split out of
// QualificaProductionDataSeeder on the user directive 2026-09-08. Each step is
// covered by its own suite (QualificaSampleLeadSeederTest,
// QualificaSampleOpportunitySeederTest, QualificaSampleRequestSeederTest);
// what is pinned HERE is that the three run together, each taking the
// Anagrafiche the previous one left free.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey()]);
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
});

it('fills all three grids in one run, sharing one pool of Anagrafiche', function (): void {
    test()->seed(QualificaSampleDataSeeder::class);

    // Step 1 owns the Anagrafiche; steps 2 and 3 hang their deals on the ones
    // it left with no open opportunity, never on a second set of their own.
    expect(Registry::query()->count())->toBe(40)
        ->and(Lead::query()->count())->toBe(40)
        ->and(Lead::query()->has('opportunity')->count())->toBe(12)
        ->and(Opportunity::query()->whereNull('lead_id')->count())->toBe(18)
        ->and(Quote::query()->count())->toBe(8);
});

it('appends a whole second dataset on re-run', function (): void {
    // User directive 2026-09-08: the chain ACCUMULATES, so it can be launched
    // as many times as rows are wanted. Step 1 brings 40 fresh Anagrafiche
    // each run, which is what keeps steps 2 and 3 fed.
    test()->seed(QualificaSampleDataSeeder::class);
    test()->seed(QualificaSampleDataSeeder::class);

    expect(Registry::query()->count())->toBe(80)
        ->and(Lead::query()->count())->toBe(80)
        ->and(Opportunity::query()->count())->toBe(60)
        ->and(Quote::query()->count())->toBe(16);
});
