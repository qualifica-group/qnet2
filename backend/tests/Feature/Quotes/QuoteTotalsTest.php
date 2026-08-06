<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\User;
use App\Models\VatRate;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

/**
 * Per-line rounding (D-12), the persisted header aggregates (D-5/D-9), the
 * full-replace-per-tab semantics (D-8/AC-036..038), and the "live product,
 * frozen amount" read (D-10/AC-055). Invokes QuoteService directly with its
 * DTOs; a handful of tests exercise StoreQuoteRequest::rules() directly
 * (Validator::make, no HTTP) since those rules already exist even though the
 * controller/route do not (MT-05).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteServiceInstance')) {
    function quoteServiceInstance(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('newSystemQuoteWorkflowStatus')) {
    /**
     * The mandatory "Aperta" (`open`) row of the GLOBAL default workflow set
     * is seeded by the quote_workflow_statuses migration itself (spec
     * 0047, moved onto the Offerta by spec 0083 D-8) — RefreshDatabase
     * already leaves it in place, so tests fetch it rather than creating a
     * second `system_key` row (unique per set).
     */
    function newSystemQuoteWorkflowStatus(): QuoteWorkflowStatus
    {
        return QuoteWorkflowStatus::whereNull('quote_workflow_id')->where('system_key', 'open')->sole();
    }
}

if (! function_exists('quoteServiceActor')) {
    function quoteServiceActor(): User
    {
        return User::factory()->create();
    }
}

if (! function_exists('createQuoteData')) {
    /**
     * @param  array<string, mixed>  $overrides
     */
    function createQuoteData(int $opportunityId, array $overrides = []): CreateQuoteData
    {
        $defaults = [
            'code' => null,
            'title' => 'Offerta di test',
            'opportunityId' => $opportunityId,
            'workflowStatusId' => null,
            'note' => null,
            'commercialId' => null,
            'commercialIdSubmitted' => false,
            'reporterId' => null,
            'reporterIdSubmitted' => false,
            'supervisorId' => null,
            'supervisorIdSubmitted' => false,
            'internalNotes' => null,
            'offerLines' => null,
            'costLines' => null,
        ];

        return new CreateQuoteData(...array_merge($defaults, $overrides));
    }
}

if (! function_exists('revenueLineProduct')) {
    /**
     * A product whose category already resolves an EFFECTIVE business
     * function (spec 0065, D-7): every test in this file exercises the
     * rounding/aggregate math, not the coverage rule (QuoteCoverageTest owns
     * AC-050/051), so a REVENUE line here must never trip
     * OpportunityProductLineCoverage's 422.
     */
    function revenueLineProduct(): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create(['category_id' => $category->id]);
    }
}

it('AC-030: quantity 3, unit_price 10.00, 22% VAT rounds to 30.00/6.60/36.60', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $product = revenueLineProduct();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 3.0, unitPrice: 10.00, vatRateId: $vatRate->id, sortOrder: null),
        ],
    ]), quoteServiceActor());

    $line = $quote->offerLines()->first();

    expect($line->net_amount)->toBe('30.00')
        ->and($line->vat_amount)->toBe('6.60')
        ->and($line->total_amount)->toBe('36.60');
});

it('AC-031: a null vat_rate_id yields vat_amount 0.00 and total_amount equal to net_amount', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $product = revenueLineProduct();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 2.0, unitPrice: 15.5, vatRateId: null, sortOrder: null),
        ],
    ]), quoteServiceActor());

    $line = $quote->offerLines()->first();

    expect($line->vat_amount)->toBe('0.00')
        ->and($line->total_amount)->toBe($line->net_amount);
});

it('AC-032: unit_price 10.01 with quantity 3 rounds half-up to net_amount 30.03', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $product = revenueLineProduct();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 3.0, unitPrice: 10.01, vatRateId: null, sortOrder: null),
        ],
    ]), quoteServiceActor());

    expect($quote->offerLines()->first()->net_amount)->toBe('30.03');
});

it('AC-032: a unit_price with more than 2 decimals is rejected by StoreQuoteRequest::rules()', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $rules = (new StoreQuoteRequest)->rules();

    $validator = Validator::make([
        'title' => 'x',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10.005],
        ],
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('offer_lines.0.unit_price'))->toBeTrue();
});

it('AC-033: submitting net_amount/vat_amount/total_amount on a line is rejected (prohibited)', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $rules = (new StoreQuoteRequest)->rules();

    $validator = Validator::make([
        'title' => 'x',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10, 'net_amount' => 30],
        ],
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('offer_lines.0.net_amount'))->toBeTrue();
});

it('AC-034: quantity 0/-1 and a negative unit_price are rejected; unit_price 0 is accepted', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $rules = (new StoreQuoteRequest)->rules();
    $base = ['title' => 'x', 'opportunity_id' => $opportunity->id];

    $withLine = fn (array $overrides) => Validator::make(array_merge($base, [
        'offer_lines' => [array_merge(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1], $overrides)],
    ]), $rules);

    expect($withLine(['quantity' => 0])->fails())->toBeTrue()
        ->and($withLine(['quantity' => -1])->fails())->toBeTrue()
        ->and($withLine(['unit_price' => -1])->fails())->toBeTrue()
        ->and($withLine(['unit_price' => 0])->fails())->toBeFalse();
});

it('AC-035: 201 rows in offer_lines is rejected (max 200)', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $rules = (new StoreQuoteRequest)->rules();

    $rows = array_fill(0, 201, ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 1]);

    $validator = Validator::make([
        'title' => 'x',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => $rows,
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('offer_lines'))->toBeTrue();
});

it('AC-036/037/038: full-replace per tab, the other tab untouched, submission order kept as sort_order', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $productA = revenueLineProduct();
    $productB = revenueLineProduct();
    $productC = Product::factory()->create();
    $service = quoteServiceInstance();

    $actor = quoteServiceActor();
    $quote = $service->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $productA->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null),
            new QuoteLineData(productId: $productB->id, quantity: 1.0, unitPrice: 20.0, vatRateId: null, sortOrder: null),
        ],
        'costLines' => [
            new QuoteLineData(productId: $productC->id, quantity: 1.0, unitPrice: 5.0, vatRateId: null, sortOrder: null),
        ],
    ]), $actor);

    expect($quote->offerLines()->pluck('product_id')->all())->toBe([$productA->id, $productB->id])
        ->and($quote->offerLines()->pluck('sort_order')->all())->toBe([0, 1]);

    $updated = $service->update($quote, new UpdateQuoteData(
        offerLines: [
            new QuoteLineData(productId: $productB->id, quantity: 1.0, unitPrice: 20.0, vatRateId: null, sortOrder: null),
        ],
    ), $actor);

    expect($updated->offerLines()->pluck('product_id')->all())->toBe([$productB->id])
        ->and($updated->costLines()->pluck('product_id')->all())->toBe([$productC->id]);

    $emptied = $service->update($updated, new UpdateQuoteData(offerLines: []), $actor);

    expect($emptied->offerLines()->count())->toBe(0);

    $untouched = $service->update($emptied, new UpdateQuoteData(title: 'Renamed', titleSubmitted: true), $actor);

    expect($untouched->costLines()->pluck('product_id')->all())->toBe([$productC->id]);
});

it('AC-040/041: header aggregates sum the already-rounded line amounts and are recalculated on every write', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $vatRate = VatRate::factory()->create(['rate' => 22]);
    $revenueProduct = revenueLineProduct();
    $costProduct = Product::factory()->create();
    $service = quoteServiceInstance();

    $actor = quoteServiceActor();
    $quote = $service->create(createQuoteData($opportunity->id, [
        'offerLines' => [new QuoteLineData(productId: $revenueProduct->id, quantity: 3.0, unitPrice: 10.0, vatRateId: $vatRate->id, sortOrder: null)],
        'costLines' => [new QuoteLineData(productId: $costProduct->id, quantity: 1.0, unitPrice: 10.0, vatRateId: $vatRate->id, sortOrder: null)],
    ]), $actor);

    expect($quote->fresh()->revenue_net)->toBe('30.00')
        ->and($quote->fresh()->revenue_vat)->toBe('6.60')
        ->and($quote->fresh()->cost_net)->toBe('10.00')
        ->and($quote->fresh()->cost_vat)->toBe('2.20')
        ->and($quote->fresh()->margin_net)->toBe('20.00');

    $updated = $service->update($quote, new UpdateQuoteData(
        offerLines: [new QuoteLineData(productId: $revenueProduct->id, quantity: 5.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
    ), $actor);

    expect($updated->revenue_net)->toBe('50.00')
        ->and($updated->revenue_vat)->toBe('0.00');
});

it('AC-042: a quote without lines has every aggregate at 0.00', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id), quoteServiceActor());

    expect($quote->revenue_net)->toBe('0.00')
        ->and($quote->revenue_vat)->toBe('0.00')
        ->and($quote->cost_net)->toBe('0.00')
        ->and($quote->cost_vat)->toBe('0.00')
        ->and($quote->margin_net)->toBe('0.00');
});

it('AC-043: cost exceeding revenue yields a negative margin_net, not clamped to zero', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $revenueProduct = revenueLineProduct();
    $costProduct = Product::factory()->create();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [new QuoteLineData(productId: $revenueProduct->id, quantity: 1.0, unitPrice: 10.0, vatRateId: null, sortOrder: null)],
        'costLines' => [new QuoteLineData(productId: $costProduct->id, quantity: 1.0, unitPrice: 50.0, vatRateId: null, sortOrder: null)],
    ]), quoteServiceActor());

    expect($quote->margin_net)->toBe('-40.00');
});

it('AC-055: the persisted line amounts stay frozen after the product changes', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $product = revenueLineProduct();
    $product->update(['name' => 'Original name']);
    $service = quoteServiceInstance();

    $quote = $service->create(createQuoteData($opportunity->id, [
        'offerLines' => [new QuoteLineData(productId: $product->id, quantity: 2.0, unitPrice: 15.0, vatRateId: null, sortOrder: null)],
    ]), quoteServiceActor());

    $line = $quote->offerLines()->first();
    $originalNet = $line->net_amount;
    $originalUnitPrice = $line->unit_price;

    $product->update(['name' => 'Renamed product']);

    $reloaded = $service->loadDetail($quote->fresh());
    $reloadedLine = $reloaded->offerLines->first();

    expect($reloadedLine->product->name)->toBe('Renamed product')
        ->and($reloadedLine->net_amount)->toBe($originalNet)
        ->and($reloadedLine->unit_price)->toBe($originalUnitPrice);
});
