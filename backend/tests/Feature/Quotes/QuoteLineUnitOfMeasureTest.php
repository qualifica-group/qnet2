<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\DataObjects\Quotes\UpdateQuoteData;
use App\Http\Requests\Quotes\StoreQuoteRequest;
use App\Http\Resources\QuoteLineResource;
use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteWorkflowStatus;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Spec 0088, D-5: quote_lines.unit_of_measure_id is frozen from the Product
 * at write time and never re-read live. Mirrors QuoteTotalsTest's low-level
 * Service-based setup (invokes QuoteService directly with its DTOs) rather
 * than the full HTTP stack, for the same reason: this file is about the
 * freeze semantics, not authorization/coverage plumbing already covered
 * elsewhere.
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteServiceInstance')) {
    function quoteServiceInstance(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('newSystemQuoteWorkflowStatus')) {
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
     * function (spec 0065, D-7), so a revenue line never trips
     * OpportunityProductLineCoverage's 422 — same helper as QuoteTotalsTest.
     */
    function revenueLineProduct(?UnitOfMeasure $unitOfMeasure = null): Product
    {
        $category = ProductCategory::factory()->create([
            'business_function_id' => BusinessFunction::factory()->create()->id,
        ]);

        return Product::factory()->create([
            'category_id' => $category->id,
            'unit_of_measure_id' => $unitOfMeasure?->id ?? UnitOfMeasure::factory()->create()->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-051 — the flagship criterion: a later product unit change never leaks
// into an already-saved quote line.
// ---------------------------------------------------------------------------

it('AC-051: changing the product\'s unit AFTER the quote line was saved leaves the line\'s frozen unit unchanged', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $originalUnit = UnitOfMeasure::factory()->create(['name' => 'Chilogrammi', 'symbol' => 'kg']);
    $product = revenueLineProduct($originalUnit);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 10.0, unitPrice: 5.0, vatRateId: null, sortOrder: null),
        ],
    ]), quoteServiceActor());

    $line = $quote->offerLines()->first();
    expect($line->unit_of_measure_id)->toBe($originalUnit->id);

    // The product's unit of measure changes AFTER the line was written.
    $newUnit = UnitOfMeasure::factory()->create(['name' => 'Grammi', 'symbol' => 'g']);
    $product->update(['unit_of_measure_id' => $newUnit->id]);

    // The line, re-fetched from the DB, still carries the ORIGINAL unit.
    $line->refresh();
    expect($line->unit_of_measure_id)->toBe($originalUnit->id)
        ->and($line->unit_of_measure_id)->not->toBe($newUnit->id);

    // The Resource's read path reflects the same freeze (D-10 for amounts,
    // D-5 for the unit): 10 Kg never silently becomes 10 Grammi.
    $detail = quoteServiceInstance()->loadDetail($quote);
    $request = Request::create('/');
    $request->setUserResolver(fn () => quoteServiceActor());
    $resourceLine = (new QuoteLineResource($detail->offerLines->first()))->toArray($request);

    expect($resourceLine['unit_of_measure'])->toBe(['id' => $originalUnit->id, 'name' => 'Chilogrammi', 'symbol' => 'kg']);
});

// AC-054 — regression found by the verifier (2026-09-01): D-8's full-replace
// convention resends EVERY row on EVERY quote save, so QuoteLineWriter::sync()
// must only re-derive `unit_of_measure_id` from the Product on the write that
// creates the row or actually changes its `product_id` — never on a resubmit
// of an otherwise-untouched line, or a later, unrelated quote edit would
// silently re-freeze the unit from whatever the product's CURRENT unit is by
// then, defeating D-5 entirely.
it('AC-054: a later quote update that resubmits the SAME line unchanged does NOT re-freeze its unit from the product\'s current value', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $originalUnit = UnitOfMeasure::factory()->create(['name' => 'Chilogrammi', 'symbol' => 'kg']);
    $product = revenueLineProduct($originalUnit);
    $actor = quoteServiceActor();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 10.0, unitPrice: 5.0, vatRateId: null, sortOrder: null),
        ],
    ]), $actor);

    $line = $quote->offerLines()->first();
    expect($line->unit_of_measure_id)->toBe($originalUnit->id);

    // The product's unit changes AFTER the line was written...
    $newUnit = UnitOfMeasure::factory()->create(['name' => 'Grammi', 'symbol' => 'g']);
    $product->update(['unit_of_measure_id' => $newUnit->id]);

    // ...then the quote is edited again, resubmitting the SAME line by id,
    // unchanged (D-8: offer_lines/cost_lines full-replace resends every row
    // on every save — this is the normal "edit quote" flow, not a special
    // case).
    $updated = quoteServiceInstance()->update($quote, new UpdateQuoteData(
        offerLines: [
            new QuoteLineData(id: $line->id, productId: $product->id, quantity: 10.0, unitPrice: 5.0, vatRateId: null, sortOrder: 0),
        ],
    ), $actor);

    expect($updated->offerLines()->first()->unit_of_measure_id)->toBe($originalUnit->id)
        ->and($updated->offerLines()->first()->unit_of_measure_id)->not->toBe($newUnit->id);
});

// AC-055 — the complementary case: a product change ON the line, in the same
// resubmit, DOES re-freeze the unit — from the NEW product, exactly like a
// create would. Guards against AC-054's fix overcorrecting into never
// updating the frozen unit at all.
it('AC-055: changing the product ON the line during an update re-freezes the unit from the NEW product', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $originalUnit = UnitOfMeasure::factory()->create();
    $product = revenueLineProduct($originalUnit);
    $newUnit = UnitOfMeasure::factory()->create();
    $newProduct = revenueLineProduct($newUnit);
    $actor = quoteServiceActor();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 10.0, unitPrice: 5.0, vatRateId: null, sortOrder: null),
        ],
    ]), $actor);

    $line = $quote->offerLines()->first();

    $updated = quoteServiceInstance()->update($quote, new UpdateQuoteData(
        offerLines: [
            new QuoteLineData(id: $line->id, productId: $newProduct->id, quantity: 10.0, unitPrice: 5.0, vatRateId: null, sortOrder: 0),
        ],
    ), $actor);

    expect($updated->offerLines()->first()->unit_of_measure_id)->toBe($newUnit->id);
});

// ---------------------------------------------------------------------------
// AC-050 — creating a line freezes the product's unit at write time
// ---------------------------------------------------------------------------

it('AC-050: creating an offer line freezes quote_lines.unit_of_measure_id from the product', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    $product = revenueLineProduct($unit);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 1.0, vatRateId: null, sortOrder: null),
        ],
    ]), quoteServiceActor());

    expect($quote->offerLines()->first()->unit_of_measure_id)->toBe($unit->id);
});

// ---------------------------------------------------------------------------
// AC-052 — unit_of_measure_id is prohibited from the client payload
// ---------------------------------------------------------------------------

it('AC-052: a payload that sends unit_of_measure_id inside an offer line is rejected (422, prohibited)', function () {
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $rules = (new StoreQuoteRequest)->rules();

    $validator = Validator::make([
        'title' => 'x',
        'opportunity_id' => $opportunity->id,
        'offer_lines' => [
            ['product_id' => $product->id, 'quantity' => 3, 'unit_price' => 10, 'unit_of_measure_id' => $product->unit_of_measure_id],
        ],
    ], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('offer_lines.0.unit_of_measure_id'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-053 — a historic NULL line falls back to the product's CURRENT unit
// ---------------------------------------------------------------------------

it('AC-053: a pre-module line (unit_of_measure_id NULL) resolves the product\'s CURRENT unit in the Resource', function () {
    newSystemQuoteWorkflowStatus();
    $opportunity = Opportunity::factory()->create();
    $currentUnit = UnitOfMeasure::factory()->create(['name' => 'Metri', 'symbol' => 'm']);
    $product = revenueLineProduct($currentUnit);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'offerLines' => [
            new QuoteLineData(productId: $product->id, quantity: 1.0, unitPrice: 1.0, vatRateId: null, sortOrder: null),
        ],
    ]), quoteServiceActor());

    $line = $quote->offerLines()->first();
    // Simulate a historic row predating the module: NULL out the frozen FK
    // directly at the DB level (QuoteLineWriter would never do this itself).
    $line->forceFill(['unit_of_measure_id' => null])->save();

    $detail = quoteServiceInstance()->loadDetail($quote->fresh());
    $request = Request::create('/');
    $request->setUserResolver(fn () => quoteServiceActor());
    $resourceLine = (new QuoteLineResource($detail->offerLines->first()))->toArray($request);

    expect($resourceLine['unit_of_measure'])->toBe(['id' => $currentUnit->id, 'name' => 'Metri', 'symbol' => 'm']);
});
