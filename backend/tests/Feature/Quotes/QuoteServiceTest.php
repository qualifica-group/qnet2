<?php

use App\DataObjects\Quotes\CreateQuoteData;
use App\DataObjects\Quotes\QuoteLineData;
use App\Models\Opportunity;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\QuoteStatus;
use App\Models\Referent;
use App\Models\User;
use App\Services\QuoteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * QuoteService's create/update/delete (spec 0065): the D-3 commercial-role
 * snapshot, the D-13 sequential/manual code, the AC-023 default status, and
 * the restrictive relations (AC-026/027). Invokes the service directly with
 * its DTOs (MT-05: the HTTP layer — controller/policy/route — does not exist
 * yet).
 */
uses(RefreshDatabase::class);

if (! function_exists('quoteServiceInstance')) {
    function quoteServiceInstance(): QuoteService
    {
        return app(QuoteService::class);
    }
}

if (! function_exists('newSystemQuoteStatus')) {
    /**
     * The mandatory "Bozza" (`new`) row is seeded by the create_quote_statuses
     * migration itself (spec 0065, D-2) — RefreshDatabase already leaves it in
     * place, so tests fetch it rather than creating a second `system_key`
     * row (unique).
     */
    function newSystemQuoteStatus(): QuoteStatus
    {
        return QuoteStatus::where('system_key', 'new')->sole();
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
            'quoteStatusId' => null,
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

it('AC-020: create without the 3 commercial roles inherits them from the opportunity', function () {
    newSystemQuoteStatus();
    $commercial = Referent::factory()->create();
    $reporter = Referent::factory()->create();
    $supervisor = User::factory()->create();
    $opportunity = Opportunity::factory()->create([
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id));

    expect($quote->commercial_id)->toBe($commercial->id)
        ->and($quote->reporter_id)->toBe($reporter->id)
        ->and($quote->supervisor_id)->toBe($supervisor->id);
});

it('AC-021: an explicitly submitted commercial_id wins over the opportunity snapshot', function () {
    newSystemQuoteStatus();
    $inherited = Referent::factory()->create();
    $explicit = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $inherited->id]);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id, [
        'commercialId' => $explicit->id,
        'commercialIdSubmitted' => true,
    ]));

    expect($quote->commercial_id)->toBe($explicit->id);
});

it('AC-022: a later change to the opportunity commercial_id does not retroactively affect an existing quote', function () {
    newSystemQuoteStatus();
    $original = Referent::factory()->create();
    $opportunity = Opportunity::factory()->create(['commercial_id' => $original->id]);

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id));

    $opportunity->update(['commercial_id' => Referent::factory()->create()->id]);

    expect($quote->fresh()->commercial_id)->toBe($original->id);
});

it('AC-023: creating without quote_status_id assigns the system new row', function () {
    $newStatus = newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id));

    expect($quote->quote_status_id)->toBe($newStatus->id);
});

it('AC-024: an opportunity accepts multiple quotes', function () {
    newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $service = quoteServiceInstance();

    $service->create(createQuoteData($opportunity->id, ['title' => 'Uno']));
    $service->create(createQuoteData($opportunity->id, ['title' => 'Due']));
    $service->create(createQuoteData($opportunity->id, ['title' => 'Tre']));

    expect(Quote::where('opportunity_id', $opportunity->id)->count())->toBe(3);
});

it('AC-026: deleting a quote cascades its lines, the product stays intact', function () {
    newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $product = Product::factory()->create();
    $service = quoteServiceInstance();

    $quote = $service->create(createQuoteData($opportunity->id, [
        'costLines' => [
            new QuoteLineData(productId: $product->id, quantity: 2.0, unitPrice: 5.0, vatRateId: null, sortOrder: null),
        ],
    ]));

    expect(QuoteLine::where('quote_id', $quote->id)->count())->toBe(1);

    $service->delete($quote);

    expect(QuoteLine::where('quote_id', $quote->id)->count())->toBe(0)
        ->and(Product::find($product->id))->not->toBeNull();
});

it('AC-027: an opportunity with at least one quote cannot be deleted', function () {
    newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    quoteServiceInstance()->create(createQuoteData($opportunity->id));

    expect(fn () => $opportunity->delete())->toThrow(QueryException::class);

    expect(Opportunity::find($opportunity->id))->not->toBeNull();
});

it('AC-066: creating without a code assigns the sequential QUO-0001', function () {
    newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();

    $quote = quoteServiceInstance()->create(createQuoteData($opportunity->id));

    expect($quote->code)->toBe('QUO-0001');
});

it('AC-067: a manual code is persisted as-is and does not break the sequence', function () {
    newSystemQuoteStatus();
    $opportunity = Opportunity::factory()->create();
    $service = quoteServiceInstance();

    $manual = $service->create(createQuoteData($opportunity->id, ['code' => 'OFF-2026/1']));
    $sequential = $service->create(createQuoteData($opportunity->id));

    expect($manual->code)->toBe('OFF-2026/1')
        ->and($sequential->code)->toBe('QUO-0001');
});
