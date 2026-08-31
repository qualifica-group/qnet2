<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Registry;
use App\Models\User;
use App\Quotes\QuoteAttributeResolver;
use App\Services\Quotes\QuoteWorkflowResolver;
use App\Support\ManagerPositions;
use Database\Seeders\DemoCatalog\DemoCategoryCatalogue;
use Database\Seeders\DemoOpportunitySeeder;
use Database\Seeders\DemoProductCategorySeeder;
use Database\Seeders\DemoProductSeeder;
use Database\Seeders\DemoQuoteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Everything a VALID quote needs: a batch of opportunities (which themselves
 * need the demo product/category catalogue), mirroring
 * DemoOpportunitySeederTest::seedOpportunityDependencies().
 */
function seedQuoteDependencies(): void
{
    Registry::factory()->count(3)->create();
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
}

it('seeds exactly one quote per opportunity, each with an offer and a cost line', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    expect(Quote::count())->toBe(Opportunity::count())
        ->and(Quote::count())->toBeGreaterThan(0);

    foreach (Quote::query()->with(['offerLines', 'costLines'])->get() as $quote) {
        expect($quote->offerLines)->not->toBeEmpty($quote->code)
            ->and($quote->costLines)->not->toBeEmpty($quote->code)
            ->and($quote->code)->toStartWith('QUO-');
    }
});

it('goes through QuoteService: aggregates persisted and offer categories cover the opportunity', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $quotes = Quote::query()->with(['opportunity.productLines', 'offerLines.product'])->get();

    expect($quotes)->not->toBeEmpty();

    foreach ($quotes as $quote) {
        expect((float) $quote->revenue_net)->toBeGreaterThan(0.0, $quote->code)
            ->and((float) $quote->cost_net)->toBeGreaterThan(0.0, $quote->code);

        $coveredCategoryIds = $quote->opportunity->productLines->pluck('product_category_id')->all();

        foreach ($quote->offerLines as $line) {
            expect($line->product->category_id)->toBeIn($coveredCategoryIds, $quote->code);
        }
    }
});

it('inherits every demo quote supervisor from its opportunity (directive 2026-08-31)', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $quotes = Quote::query()->with('opportunity')->get();
    $sawAtLeastOneSupervisor = false;

    foreach ($quotes as $quote) {
        // An unconditional copy: no Gestore Account filter any more, so the
        // two columns match even when the supervisor holds no manager slot.
        expect($quote->supervisor_id)->toBe($quote->opportunity->supervisor_id, $quote->code);

        $sawAtLeastOneSupervisor = $sawAtLeastOneSupervisor || $quote->supervisor_id !== null;
    }

    // A dataset where NO opportunity carries a supervisor would make the
    // assertion above vacuous: DemoOpportunitySeeder fills the column.
    expect($sawAtLeastOneSupervisor)->toBeTrue();
});

it('starts the offer unit price from the product price and the cost line from the product cost (D-6)', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $quote = Quote::query()->with(['offerLines.product', 'costLines.product'])->firstOrFail();

    foreach ($quote->offerLines as $line) {
        expect((float) $line->unit_price)->toBe((float) $line->product->price);
    }

    foreach ($quote->costLines as $line) {
        expect((float) $line->unit_price)->toBe((float) $line->product->cost);
    }
});

it('AC-061: every demo quote carries a quote_workflow_status_id belonging to its own resolved set', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $resolver = app(QuoteWorkflowResolver::class);

    foreach (Quote::query()->with('offerLines.product.category', 'opportunity')->get() as $quote) {
        $allowedIds = $resolver->statusesFor($resolver->resolve($quote))->pluck('id');

        expect($allowedIds->contains($quote->quote_workflow_status_id))->toBeTrue($quote->code);
    }
});

it('is idempotent: re-running does not duplicate or orphan quotes', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);
    $firstCount = Quote::count();
    $firstCodes = Quote::query()->pluck('code')->sort()->values()->all();

    test()->seed(DemoQuoteSeeder::class);

    expect(Quote::count())->toBe($firstCount)
        ->and(Quote::query()->pluck('code')->sort()->values()->all())->toBe($firstCodes);
});

it('lets Opportunity::delete() run right after Quote::delete(), the exact DemoDataSeeder pre-clear order', function (): void {
    // Quote::opportunity_id is restrictOnDelete (never cascade): if
    // DemoDataSeeder ever deleted opportunities BEFORE their quotes on a
    // re-run, this would throw a foreign key violation. This reproduces
    // DemoDataSeeder's own pre-clear order without paying for its full
    // pipeline (DatabaseSeeder + every other Demo*Seeder).
    seedQuoteDependencies();
    test()->seed(DemoQuoteSeeder::class);

    expect(Quote::count())->toBeGreaterThan(0);

    Quote::query()->delete();
    Opportunity::query()->delete();

    expect(Opportunity::count())->toBe(0)
        ->and(Quote::count())->toBe(0);
});

it('AC-050: every demo quote with a QUOTE-context applicable attribute carries a coherent attribute_values map', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $resolver = app(QuoteAttributeResolver::class);
    $sawAtLeastOneValue = false;

    foreach (Quote::query()->with('offerLines.product.category')->get() as $quote) {
        $applicableCodes = $resolver->resolve($quote)->pluck('code')->all();

        // Never a value for a code the quote's own categories do not carry.
        expect(array_keys($quote->attribute_values ?? []))->each->toBeIn($applicableCodes, $quote->code);

        if (($quote->attribute_values ?? []) !== []) {
            $sawAtLeastOneValue = true;
        }
    }

    expect($sawAtLeastOneValue)->toBeTrue();
});

it('AC-015: every demo quote has a populated GA2 "Operatore" slot, coherent with operator_id', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);

    $quotes = Quote::query()->with('managers')->get();
    expect($quotes)->not->toBeEmpty();

    foreach ($quotes as $quote) {
        $operatorManager = $quote->managers->first(
            fn ($manager): bool => (int) $manager->pivot->position === ManagerPositions::OPERATOR,
        );

        expect($operatorManager)->not->toBeNull($quote->code)
            ->and($quote->operator_id)->toBe($operatorManager->id, $quote->code)
            ->and($quote->managers)->toHaveCount(2, $quote->code);
    }
});

it('AC-015: re-running is idempotent on the managers pivot too (no duplicate/orphan quote_user rows)', function (): void {
    seedQuoteDependencies();

    test()->seed(DemoQuoteSeeder::class);
    $firstPivotCount = DB::table('quote_user')->count();

    test()->seed(DemoQuoteSeeder::class);

    expect(DB::table('quote_user')->count())->toBe($firstPivotCount);
});

it('seeds nothing when there is no opportunity (nor an offer to build one)', function (): void {
    // Neither an Opportunity nor an "offer" category exists here — the exact
    // pair of prerequisites the guard checks (opportunities/hasOffers) —
    // mirroring DemoOpportunitySeederTest's own "seeds nothing" case, since
    // DemoOpportunitySeeder shares this guard and never leaves a half-built
    // opportunity behind for this seeder to attach a quote to.
    Registry::factory()->count(2)->create();
    User::factory()->count(3)->create();

    test()->seed(DemoQuoteSeeder::class);

    expect(Quote::count())->toBe(0);
});
