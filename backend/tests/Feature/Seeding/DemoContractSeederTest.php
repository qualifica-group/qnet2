<?php

use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\Contract;
use App\Models\OperationalSite;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoContractSeeder;
use Database\Seeders\DemoOpportunitySeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\DemoQuoteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The seeded Offerte the contracts are born from, mirroring
 * DemoQuoteSeederTest::seedQuoteDependencies().
 */
function seedContractDependencies(): void
{
    // Enough offers for the stride to reach all five lifecycle shapes.
    Registry::factory()->count(12)->create();
    Company::factory()->count(2)->create();
    CompanySite::factory()->count(2)->create();
    OperationalSite::factory()->count(2)->create();
    User::factory()->count(8)->create();

    foreach (DemoCategoryCatalogue::TREE as $branch) {
        BusinessFunction::query()->firstOrCreate(['name' => $branch['business_function']]);
    }

    test()->seed(DemoProductCategorySeeder::class);
    test()->seed(DemoProductSeeder::class);
    test()->seed(DemoOpportunitySeeder::class);
    test()->seed(DemoQuoteSeeder::class);
}

it('opens contracts through the quote lifecycle, only on offers closed as won', function (): void {
    seedContractDependencies();

    test()->seed(DemoContractSeeder::class);

    $contracts = Contract::query()->with(['quote.quoteWorkflowStatus', 'contractStatus'])->get();

    expect($contracts)->not->toBeEmpty()
        ->and($contracts->count())->toBeLessThan(Quote::count());

    foreach ($contracts as $contract) {
        expect($contract->accepted_at)->not->toBeNull();

        $quoteGroup = $contract->quote->quoteWorkflowStatus->group;

        if ($contract->isSuspended()) {
            expect($quoteGroup)->not->toBe(WorkflowStatusGroup::ClosedWon);
        } else {
            expect($quoteGroup)->toBe(WorkflowStatusGroup::ClosedWon);
        }
    }
});

it('covers every lifecycle shape: to validate, validated, working, terminated, suspended', function (): void {
    seedContractDependencies();

    test()->seed(DemoContractSeeder::class);

    $contracts = Contract::query()->with('contractStatus')->get();

    expect($contracts->count())->toBeGreaterThanOrEqual(5)
        ->and($contracts->whereNotNull('validated_at')->every(
            fn (Contract $contract): bool => $contract->contractStatus->group === ContractStatusGroup::ClosedWon,
        ))->toBeTrue()
        ->and($contracts->whereNotNull('validated_at'))->not->toBeEmpty()
        ->and($contracts->whereNotNull('terminated_at'))->not->toBeEmpty()
        ->and($contracts->filter->isSuspended())->not->toBeEmpty()
        ->and($contracts->whereNotNull('expiry_date'))->not->toBeEmpty();
});

it('is idempotent: re-running does not duplicate contracts', function (): void {
    seedContractDependencies();

    test()->seed(DemoContractSeeder::class);
    $firstQuoteIds = Contract::query()->orderBy('quote_id')->pluck('quote_id')->all();

    test()->seed(DemoContractSeeder::class);

    expect(Contract::query()->orderBy('quote_id')->pluck('quote_id')->all())->toBe($firstQuoteIds);
});

it('seeds nothing when there is no quote', function (): void {
    User::factory()->count(2)->create();

    test()->seed(DemoContractSeeder::class);

    expect(Contract::count())->toBe(0);
});
