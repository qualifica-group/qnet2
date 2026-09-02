<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductTypology;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Quotes\QuoteTypologySummaryCalculator;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Spec 0099, D-6/D-7: the "Riepilogo per Tipologia Prodotto" aggregate.
 * Mirrors QuoteLineUnitOfMeasureTest's low-level Service-based setup
 * (QuoteService invoked directly with its DTOs) rather than the full HTTP
 * stack: this file is about the arithmetic and the dynamic typology set, not
 * authorization plumbing covered elsewhere.
 */
uses(RefreshDatabase::class);

if (! function_exists('typologySummaryQuoteData')) {
    /**
     * @param  array<int, QuoteLineData>|null  $offerLines
     * @param  array<int, QuoteLineData>|null  $costLines
     */
    function typologySummaryQuoteData(int $opportunityId, ?array $offerLines, ?array $costLines = null): CreateQuoteData
    {
        return new CreateQuoteData(
            code: null,
            title: 'Offerta di test',
            opportunityId: $opportunityId,
            workflowStatusId: null,
            note: null,
            commercialId: null,
            commercialIdSubmitted: false,
            reporterId: null,
            reporterIdSubmitted: false,
            supervisorId: null,
            supervisorIdSubmitted: false,
            internalNotes: null,
            offerLines: $offerLines,
            costLines: $costLines,
        );
    }
}

if (! function_exists('typologyLineProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (spec 0065, D-7), so a revenue line never trips
     * OpportunityProductLineCoverage's 422.
     */
    function typologyLineProduct(ProductTypology $typology): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'product_typology_id' => $typology->id,
        ]);
    }
}

if (! function_exists('typologySummaryOf')) {
    /**
     * @return array<string, string> name => net
     */
    function typologySummaryOf(Quote $quote): array
    {
        return collect(app(QuoteTypologySummaryCalculator::class)->totals($quote))
            ->mapWithKeys(fn (array $entry): array => [$entry['name'] => $entry['net']])
            ->all();
    }
}

beforeEach(function () {
    QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    // The migration seeds "Ente" (AC-002); the module's second initial row is
    // added here so the fixtures match the brief's own example.
    ProductTypology::firstOrCreate(['code' => 'consultancy'], ['name' => 'Consulenza']);
});

// ---------------------------------------------------------------------------
// AC-041 — the brief's worked example
// ---------------------------------------------------------------------------

it('AC-041: groups and sums the offer lines by their product typology', function () {
    $ente = ProductTypology::where('code', 'institution')->sole();
    $consulenza = ProductTypology::where('code', 'consultancy')->sole();
    $opportunity = Opportunity::factory()->create();

    $productA = typologyLineProduct($ente);
    $productB = typologyLineProduct($ente);
    $productC = typologyLineProduct($consulenza);

    $quote = app(QuoteService::class)->create(typologySummaryQuoteData($opportunity->id, [
        new QuoteLineData(productId: $productA->id, quantity: 1.0, unitPrice: 2000.0, vatRateId: null, sortOrder: null),
        new QuoteLineData(productId: $productB->id, quantity: 1.0, unitPrice: 3000.0, vatRateId: null, sortOrder: null),
        new QuoteLineData(productId: $productC->id, quantity: 1.0, unitPrice: 2500.0, vatRateId: null, sortOrder: null),
    ]), User::factory()->create());

    $summary = typologySummaryOf($quote);

    expect($summary['Ente'])->toBe('5000.00')
        ->and($summary['Consulenza'])->toBe('2500.00');
});

// ---------------------------------------------------------------------------
// AC-042 — cost lines never contribute
// ---------------------------------------------------------------------------

it('AC-042: cost-tab lines do not contribute to any bucket', function () {
    $ente = ProductTypology::where('code', 'institution')->sole();
    $opportunity = Opportunity::factory()->create();
    $product = typologyLineProduct($ente);

    $quote = app(QuoteService::class)->create(typologySummaryQuoteData(
        $opportunity->id,
        [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 1000.0, vatRateId: null, sortOrder: null)],
        [new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 400.0, vatRateId: null, sortOrder: null)],
    ), User::factory()->create());

    expect(typologySummaryOf($quote)['Ente'])->toBe('1000.00');
});

// ---------------------------------------------------------------------------
// AC-050 — the invariant: the buckets reconcile with revenue.net
// ---------------------------------------------------------------------------

it('AC-050: the buckets sum exactly to the offer revenue net, with decimals, quantities and mixed VAT', function () {
    $ente = ProductTypology::where('code', 'institution')->sole();
    $consulenza = ProductTypology::where('code', 'consultancy')->sole();
    $formazione = ProductTypology::factory()->create(['name' => 'Formazione', 'code' => 'training']);
    $opportunity = Opportunity::factory()->create();

    $vat22 = VatRate::factory()->create(['rate' => 22]);
    $vat10 = VatRate::factory()->create(['rate' => 10]);

    $quote = app(QuoteService::class)->create(typologySummaryQuoteData($opportunity->id, [
        new QuoteLineData(productId: typologyLineProduct($ente)->id, quantity: 3.0, unitPrice: 133.33, vatRateId: $vat22->id, sortOrder: null),
        new QuoteLineData(productId: typologyLineProduct($ente)->id, quantity: 7.5, unitPrice: 19.99, vatRateId: $vat10->id, sortOrder: null),
        new QuoteLineData(productId: typologyLineProduct($consulenza)->id, quantity: 1.25, unitPrice: 800.4, vatRateId: $vat22->id, sortOrder: null),
        new QuoteLineData(productId: typologyLineProduct($formazione)->id, quantity: 2.0, unitPrice: 0.01, vatRateId: null, sortOrder: null),
    ]), User::factory()->create());

    $buckets = app(QuoteTypologySummaryCalculator::class)->totals($quote);
    $bucketSum = round(collect($buckets)->sum(fn (array $entry): float => (float) $entry['net']), 2);

    expect($bucketSum)->toBe(round((float) $quote->fresh()->revenue_net, 2));
});

// ---------------------------------------------------------------------------
// AC-051/052/053 — every configured typology, zero-filled and dynamic
// ---------------------------------------------------------------------------

it('AC-051: a configured typology with no line in the offer still appears, at 0.00', function () {
    $ente = ProductTypology::where('code', 'institution')->sole();
    $opportunity = Opportunity::factory()->create();

    $quote = app(QuoteService::class)->create(typologySummaryQuoteData($opportunity->id, [
        new QuoteLineData(productId: typologyLineProduct($ente)->id, quantity: 1.0, unitPrice: 100.0, vatRateId: null, sortOrder: null),
    ]), User::factory()->create());

    $summary = typologySummaryOf($quote);

    expect($summary)->toHaveKey('Consulenza')
        ->and($summary['Consulenza'])->toBe('0.00')
        ->and($summary['Ente'])->toBe('100.00');
});

it('AC-052: an offer with no lines lists every typology at 0.00, never an empty block', function () {
    $opportunity = Opportunity::factory()->create();

    $quote = app(QuoteService::class)->create(
        typologySummaryQuoteData($opportunity->id, null),
        User::factory()->create(),
    );

    $summary = typologySummaryOf($quote);

    expect($summary)->toHaveCount(ProductTypology::count())
        ->and(array_unique(array_values($summary)))->toBe(['0.00']);
});

it('AC-053: a typology created AFTER the offer appears in its summary with no code change', function () {
    $ente = ProductTypology::where('code', 'institution')->sole();
    $opportunity = Opportunity::factory()->create();

    $quote = app(QuoteService::class)->create(typologySummaryQuoteData($opportunity->id, [
        new QuoteLineData(productId: typologyLineProduct($ente)->id, quantity: 1.0, unitPrice: 100.0, vatRateId: null, sortOrder: null),
    ]), User::factory()->create());

    expect(typologySummaryOf($quote))->not->toHaveKey('Formazione');

    ProductTypology::factory()->create(['name' => 'Formazione', 'code' => 'training']);

    expect(typologySummaryOf($quote)['Formazione'])->toBe('0.00');
});

it('orders the buckets by typology name', function () {
    ProductTypology::factory()->create(['name' => 'Alfa', 'code' => 'alfa']);
    $opportunity = Opportunity::factory()->create();

    $quote = app(QuoteService::class)->create(
        typologySummaryQuoteData($opportunity->id, null),
        User::factory()->create(),
    );

    $names = collect(app(QuoteTypologySummaryCalculator::class)->totals($quote))->pluck('name')->all();

    expect($names)->toBe(collect($names)->sort()->values()->all());
});
