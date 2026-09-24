<?php

use App\Enums\CategoryManagementMode;
use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\Contract;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\QualificaSampleContractSeeder;
use Database\Seeders\QualificaSampleOpportunitySeeder;
use Database\Seeders\QualificaSampleQuoteSeeder;
use Database\Seeders\QualificaSampleWorkOrderSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Steps 4-6 of the sample dataset (user directive 2026-09-24): Offerte on the
// batch's opportunities, Contratti opened by closing some of them as won,
// Commesse generated through "Programma" — each step through the system's own
// write path and rules, and confined to the running batch's opportunities.
uses(RefreshDatabase::class);

/** Standalone opportunities the flow is seeded on. */
const DEAL_FLOW_OPPORTUNITIES = 8;

/**
 * One offer category (business function + product) with the given branch
 * rules, a Sede, three users and DEAL_FLOW_OPPORTUNITIES quote-less deals.
 *
 * @param  array<string, mixed>  $categoryRules
 */
function seedDealFlowOpportunities(array $categoryRules = []): void
{
    $category = ProductCategory::factory()->create(['business_function_id' => BusinessFunction::factory(), ...$categoryRules]);
    Product::factory()->count(2)->create(['category_id' => $category->getKey()]);
    OperationalSite::factory()->create();
    User::factory()->count(3)->create();
    Registry::factory()->count(DEAL_FLOW_OPPORTUNITIES)->create();

    app(QualificaSampleOpportunitySeeder::class)->run(opportunities: DEAL_FLOW_OPPORTUNITIES);
}

describe('QualificaSampleQuoteSeeder', function (): void {
    it('gives every quote-less opportunity of the batch one Offerta, on its own categories', function (): void {
        seedDealFlowOpportunities();

        test()->seed(QualificaSampleQuoteSeeder::class);

        $quotes = Quote::query()->with(['offerLines.product', 'opportunity.productLines', 'quoteWorkflowStatus'])->get();

        expect($quotes)->toHaveCount(DEAL_FLOW_OPPORTUNITIES)
            ->and($quotes->pluck('opportunity_id')->unique())->toHaveCount(DEAL_FLOW_OPPORTUNITIES)
            ->and($quotes->filter(fn (Quote $quote): bool => $quote->offerLines->isEmpty()))->toBeEmpty()
            // Coverage is satisfied by a category the opportunity already
            // carries, never by one QuoteService has to append.
            ->and($quotes->filter(fn (Quote $quote): bool => $quote->offerLines->contains(
                fn ($line): bool => ! $quote->opportunity->productLines->pluck('product_category_id')->contains($line->product->category_id),
            )))->toBeEmpty()
            // Closing as won belongs to the contract step, not to this one.
            ->and($quotes->filter(fn (Quote $quote): bool => $quote->quoteWorkflowStatus->group === WorkflowStatusGroup::ClosedWon))->toBeEmpty()
            ->and($quotes->filter(fn (Quote $quote): bool => $quote->quoteWorkflowStatus->group === WorkflowStatusGroup::ClosedLost))->not->toBeEmpty();
    });

    it('prices a single-managed branch with exactly one row of quantity one', function (): void {
        seedDealFlowOpportunities(['management_mode' => CategoryManagementMode::Single, 'single_quote_per_opportunity' => true]);

        test()->seed(QualificaSampleQuoteSeeder::class);

        $quotes = Quote::query()->with('offerLines')->get();

        expect($quotes)->toHaveCount(DEAL_FLOW_OPPORTUNITIES)
            ->and($quotes->filter(fn (Quote $quote): bool => $quote->offerLines->count() !== 1))->toBeEmpty()
            ->and($quotes->filter(fn (Quote $quote): bool => (float) $quote->offerLines->first()->quantity !== 1.0))->toBeEmpty();
    });

    it('never touches an opportunity at or below the watermark', function (): void {
        seedDealFlowOpportunities();
        $watermark = (int) Opportunity::query()->orderBy('id')->skip(DEAL_FLOW_OPPORTUNITIES / 2)->value('id') - 1;

        app(QualificaSampleQuoteSeeder::class)->run(sinceOpportunityId: $watermark);

        expect(Quote::query()->count())->toBe(DEAL_FLOW_OPPORTUNITIES / 2)
            ->and(Quote::query()->where('opportunity_id', '<=', $watermark)->exists())->toBeFalse();
    });
});

describe('QualificaSampleContractSeeder', function (): void {
    it('opens one contract per offer it closes as won, over every lifecycle shape', function (): void {
        seedDealFlowOpportunities();
        test()->seed(QualificaSampleQuoteSeeder::class);
        $open = Quote::query()->whereHas('quoteWorkflowStatus', fn ($query) => $query->where('group', '!=', WorkflowStatusGroup::ClosedLost->value))->count();

        app(QualificaSampleContractSeeder::class)->run(contracts: DEAL_FLOW_OPPORTUNITIES);

        $groups = Contract::query()->with('contractStatus')->get()->map(fn (Contract $contract) => $contract->contractStatus->group);

        // Only the offers still in a working group can close positively.
        expect($groups)->toHaveCount($open)
            ->and($groups->filter(fn (ContractStatusGroup $group): bool => $group === ContractStatusGroup::ClosedWon))->not->toBeEmpty()
            ->and($groups->filter(fn (ContractStatusGroup $group): bool => $group === ContractStatusGroup::ClosedLost))->not->toBeEmpty()
            ->and($groups->filter(fn (ContractStatusGroup $group): bool => $group === ContractStatusGroup::Open))->not->toBeEmpty();
    });

    it('closes nothing on a branch that is not sold under a contract', function (): void {
        // Spec 0091: "Formazione" closing positively opens no contract, so the
        // seeder does not close it at all.
        seedDealFlowOpportunities(['generates_contract' => false]);
        test()->seed(QualificaSampleQuoteSeeder::class);

        test()->seed(QualificaSampleContractSeeder::class);

        expect(Contract::query()->count())->toBe(0)
            ->and(Quote::query()->whereHas('quoteWorkflowStatus', fn ($query) => $query->where('group', WorkflowStatusGroup::ClosedWon->value))->exists())->toBeFalse();
    });

    it('sizes its batch from the run() argument', function (): void {
        seedDealFlowOpportunities();
        test()->seed(QualificaSampleQuoteSeeder::class);

        app(QualificaSampleContractSeeder::class)->run(contracts: 2);

        expect(Contract::query()->count())->toBe(2);
    });
});

describe('QualificaSampleWorkOrderSeeder', function (): void {
    it('programs every validated contract into one commessa carrying all its offer rows', function (): void {
        seedDealFlowOpportunities();
        test()->seed(QualificaSampleQuoteSeeder::class);
        app(QualificaSampleContractSeeder::class)->run(contracts: DEAL_FLOW_OPPORTUNITIES);
        $validated = Contract::query()->whereHas('contractStatus', fn ($query) => $query->where('group', ContractStatusGroup::ClosedWon->value))->count();

        app(QualificaSampleWorkOrderSeeder::class)->run(workOrders: DEAL_FLOW_OPPORTUNITIES);

        $workOrders = WorkOrder::query()->with(['quoteLines', 'supervisors', 'quote.offerLines', 'quote.contract.contractStatus'])->get();

        expect($validated)->toBeGreaterThan(0)
            ->and($workOrders)->toHaveCount($validated)
            ->and($workOrders->filter(fn (WorkOrder $workOrder): bool => ! str_starts_with($workOrder->code, 'COM-')))->toBeEmpty()
            ->and($workOrders->filter(fn (WorkOrder $workOrder): bool => $workOrder->supervisors->isEmpty()))->toBeEmpty()
            ->and($workOrders->filter(fn (WorkOrder $workOrder): bool => $workOrder->quoteLines->count() !== $workOrder->quote->offerLines->count()))->toBeEmpty()
            ->and($workOrders->filter(fn (WorkOrder $workOrder): bool => $workOrder->quote->contract->contractStatus->group !== ContractStatusGroup::ClosedWon))->toBeEmpty();
    });

    it('programs nothing more once every offer row has its commessa', function (): void {
        // Spec 0095, D-4: one row, one commessa.
        seedDealFlowOpportunities();
        test()->seed(QualificaSampleQuoteSeeder::class);
        app(QualificaSampleContractSeeder::class)->run(contracts: DEAL_FLOW_OPPORTUNITIES);

        test()->seed(QualificaSampleWorkOrderSeeder::class);
        $first = WorkOrder::query()->count();
        test()->seed(QualificaSampleWorkOrderSeeder::class);

        expect(WorkOrder::query()->count())->toBe($first);
    });
});
