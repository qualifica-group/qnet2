<?php

use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Tables\ProformaRequestsTableDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('AC-010: a multi-offer work order raises one request per offer, each with its own offer and payment method', function () {
    Sanctum::actingAs(proformaUserWith(['create', 'viewAny', 'view']));
    [$workOrder, $quoteA, $quoteB] = multiQuoteWorkOrder();

    $data = $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'Bill both'])
        ->assertCreated()->json('data');

    expect($data)->toHaveCount(2);
    $rows = ProformaRequest::query()->where('work_order_id', $workOrder->id)->get()->keyBy('quote_id');
    expect($rows->keys()->sort()->values()->all())->toBe(collect([$quoteA->id, $quoteB->id])->sort()->values()->all())
        ->and($rows[$quoteA->id]->payment_method_id)->toBe($quoteA->payment_method_id)
        ->and($rows[$quoteB->id]->payment_method_id)->toBe($quoteB->payment_method_id)
        ->and($rows[$quoteA->id]->payment_method_id)->not->toBe($rows[$quoteB->id]->payment_method_id);

    $byQuote = collect($data)->keyBy('quote.id');
    expect($byQuote[$quoteA->id]['quote'])->toEqual(['id' => $quoteA->id, 'code' => $quoteA->code])
        ->and($byQuote[$quoteA->id]['company']['id'])->toBe($quoteA->company_id)
        ->and($byQuote[$quoteB->id]['customer']['id'])->toBe($quoteB->opportunity->registry_id);
});

it('AC-010: the proforma summary of a multi-offer work order does not crash and has no single payment method', function () {
    Sanctum::actingAs(proformaUserWith(['create', 'viewAny', 'view']));
    [$workOrder] = multiQuoteWorkOrder();

    $this->getJson("/api/work-orders/{$workOrder->id}/proforma-requests/summary")
        ->assertOk()->assertJsonPath('data.payment_method', null)->assertJsonPath('data.status', 'none');
});

it('AC-010: the proforma requests table shows the company, customer and offer of each request own offer', function () {
    Sanctum::actingAs(proformaUserWith(['create', 'viewAny', 'view']));
    [$workOrder, $quoteA, $quoteB] = multiQuoteWorkOrder();
    $this->postJson("/api/work-orders/{$workOrder->id}/proforma-requests", ['note' => 'x'])->assertCreated();

    $rows = collect(app(ProformaRequestsTableDefinition::class)->baseQuery()->get())
        ->map(fn (ProformaRequest $row) => app(ProformaRequestsTableDefinition::class)->mapRow(auth()->user(), $row))
        ->keyBy(fn (array $row) => $row['quote']['id']);

    expect($rows[$quoteA->id]['company']['id'])->toBe($quoteA->company_id)
        ->and($rows[$quoteB->id]['company']['id'])->toBe($quoteB->company_id)
        ->and($rows[$quoteB->id]['customer']['id'])->toBe(Quote::find($quoteB->id)->opportunity->registry_id);
});
