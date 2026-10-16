<?php

use App\Enums\InvoiceType;
use App\Models\Invoice;
use App\Models\ProformaRequest;
use App\Models\Quote;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/** @return array{0: WorkOrder, 1: Quote, 2: Quote, 3: ProformaRequest, 4: ProformaRequest} */
function multiQuoteRequests(): array
{
    [$workOrder, $quoteA, $quoteB] = multiQuoteWorkOrder();
    $requests = ProformaRequest::query()->where('work_order_id', $workOrder->id)->exists() ? null : [
        ProformaRequest::factory()->create(['work_order_id' => $workOrder->id, 'quote_id' => $quoteA->id, 'kind' => 'consultancy', 'payment_method_id' => $quoteA->payment_method_id]),
        ProformaRequest::factory()->create(['work_order_id' => $workOrder->id, 'quote_id' => $quoteB->id, 'kind' => 'consultancy', 'payment_method_id' => $quoteB->payment_method_id]),
    ];

    return [$workOrder, $quoteA, $quoteB, ...$requests];
}

it('AC-011: the draft of a request proposes company, payment, customer and lines of its own offer only', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    [, $quoteA, $quoteB, $requestA, $requestB] = multiQuoteRequests();

    $draftA = $this->getJson("/api/proforma-requests/{$requestA->id}/invoice-draft")->assertOk()->json('data');
    $draftB = $this->getJson("/api/proforma-requests/{$requestB->id}/invoice-draft")->assertOk()->json('data');

    expect($draftA['quote'])->toEqual(['id' => $quoteA->id, 'code' => $quoteA->code])
        ->and($draftA['defaults']['company']['id'])->toBe($quoteA->company_id)
        ->and($draftA['defaults']['payment_method']['id'])->toBe($quoteA->payment_method_id)
        ->and($draftA['defaults']['customer']['id'])->toBe($quoteA->opportunity->registry_id)
        ->and(array_column($draftA['available_lines'], 'quote_line_id'))->toBe($quoteA->lines()->pluck('id')->all())
        ->and($draftB['defaults']['company']['id'])->toBe($quoteB->company_id)
        ->and(array_column($draftB['available_lines'], 'quote_line_id'))->toBe($quoteB->lines()->pluck('id')->all());
});

it('AC-011: the issued document carries the offer of the request and shows in the right customer ledger', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    [, $quoteA, $quoteB, $requestA, $requestB] = multiQuoteRequests();

    $payload = multiQuotePayload($quoteB, $quoteB->lines()->value('id'));

    $id = $this->postJson("/api/proforma-requests/{$requestB->id}/invoice", $payload)->assertCreated()->json('data.id');

    $invoice = Invoice::query()->findOrFail($id);
    expect($invoice->quote_id)->toBe($quoteB->id)->and($requestA->fresh()->status->value)->toBe('pending');

    $invoice->update(['type' => InvoiceType::Invoice]);
    foreach (['ledger.view', 'invoices.view', 'purchase-invoices.view'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    $viewer = User::factory()->create();
    $viewer->givePermissionTo(['ledger.view', 'invoices.view', 'purchase-invoices.view']);
    Sanctum::actingAs($viewer);

    $documents = $this->getJson("/api/registries/{$quoteB->opportunity->registry_id}/ledger?date_from=2026-01-01&company_site_id[]={$quoteB->company_site_id}")
        ->assertOk()->json('data.customer.documents');
    $other = $this->getJson("/api/registries/{$quoteB->opportunity->registry_id}/ledger?date_from=2026-01-01&company_site_id[]={$quoteA->company_site_id}")
        ->assertOk()->json('data.customer.documents');

    expect(array_column($documents, 'id'))->toBe([$id])->and($other)->toBe([]);
});

it('AC-011: a line of another offer of the same work order is refused on issue', function () {
    Sanctum::actingAs(invoiceUserWith(['create']));
    [, $quoteA, $quoteB, $requestA] = multiQuoteRequests();

    $payload = multiQuotePayload($quoteA, $quoteB->lines()->value('id'));

    $this->postJson("/api/proforma-requests/{$requestA->id}/invoice", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('lines.0.quote_line_id');
    expect(Invoice::query()->count())->toBe(0);
});
