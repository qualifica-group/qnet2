<?php

use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\QualificaSampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// The single entry point for the FABRICATED pipeline, split out of
// QualificaProductionDataSeeder on the user directive 2026-09-08. Each step is
// covered by its own suite (QualificaSampleLeadSeederTest,
// QualificaSampleOpportunitySeederTest, QualificaSampleRequestSeederTest,
// QualificaSampleDealFlowSeederTest); what is pinned HERE is that they run
// together, each taking what the previous one left: the free Anagrafiche for
// steps 1-3, the Offerta -> Contratto -> Commessa chain for steps 4-6.
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory()]);
    Product::factory()->create(['category_id' => $category->getKey()]);
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    TaskType::factory()->create();
});

it('fills every grid in one run, sharing one pool of Anagrafiche', function (): void {
    test()->seed(QualificaSampleDataSeeder::class);

    // Step 1 owns the Anagrafiche; steps 2 and 3 hang their deals on the ones
    // it left with no open opportunity, never on a second set of their own.
    // User directive 2026-09-29: the one category still to cover adds one
    // lead, one contract and one commessa on top of the batch — its deal is
    // one of the converted leads, its offer the request's.
    expect(Registry::query()->count())->toBe(41)
        ->and(Lead::query()->count())->toBe(41)
        ->and(Lead::query()->has('opportunity')->count())->toBe(12)
        ->and(Opportunity::query()->whereNull('lead_id')->count())->toBe(18)
        // 8 born with a request + one Offerta on 15 of the 22 other deals
        // (user directive 2026-09-24).
        ->and(Quote::query()->count())->toBe(23)
        ->and(Contract::query()->count())->toBe(9)
        ->and(WorkOrder::query()->count())->toBe(5)
        // 60 day-to-day entries + the one each of the 10 completed Tasks logs.
        ->and(Task::query()->count())->toBe(30)
        ->and(TimeEntry::query()->count())->toBe(70);
});

it('appends a whole second dataset on re-run', function (): void {
    // User directive 2026-09-08: the chain ACCUMULATES, so it can be launched
    // as many times as rows are wanted. Step 1 brings 40 fresh Anagrafiche
    // each run, which is what keeps steps 2 and 3 fed. The category coverage
    // (user directive 2026-09-29) is paid once: the first run covers it.
    test()->seed(QualificaSampleDataSeeder::class);
    test()->seed(QualificaSampleDataSeeder::class);

    expect(Registry::query()->count())->toBe(81)
        ->and(Lead::query()->count())->toBe(81)
        ->and(Opportunity::query()->count())->toBe(60)
        ->and(Quote::query()->count())->toBe(46)
        ->and(Contract::query()->count())->toBe(17)
        ->and(WorkOrder::query()->count())->toBe(9)
        ->and(Task::query()->count())->toBe(60)
        ->and(TimeEntry::query()->count())->toBe(140);
});
