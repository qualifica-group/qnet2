<?php

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\ProformaRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * @return array{0: ProformaRequest, 1: array<string, mixed>, 2: Product}
 */
function classifiedInvoiceRequest(?int $productRevenueTypeId): array
{
    $request = invoiceRequest();
    $product = Product::query()->findOrFail($request->workOrder->quoteLines()->value('quote_lines.product_id'));
    $product->update(['revenue_type_id' => $productRevenueTypeId]);
    $payload = invoicePayload($request);
    $payload['lines'][0]['product_id'] = $product->id;

    return [$request, $payload, $product];
}

it('AC-003: an absent key saves the proposal, a value saves it, an explicit null saves null', function () {
    $proposed = revenueSubAccount();
    $chosen = revenueSubAccount();
    Sanctum::actingAs(invoiceUserWith(['create']));

    [$request, $payload] = classifiedInvoiceRequest($proposed->id);
    $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)
        ->assertCreated()
        ->assertJsonPath('data.lines.0.revenue_type_id', $proposed->id)
        ->assertJsonPath('data.lines.0.revenue_type.id', $proposed->id)
        ->assertJsonPath('data.lines.0.revenue_type.code', $proposed->code);

    [$request, $payload] = classifiedInvoiceRequest($proposed->id);
    $payload['lines'][0]['revenue_type_id'] = $chosen->id;
    $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertCreated()->assertJsonPath('data.lines.0.revenue_type_id', $chosen->id);

    [$request, $payload] = classifiedInvoiceRequest($proposed->id);
    $payload['lines'][0]['revenue_type_id'] = null;
    $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertCreated()->assertJsonPath('data.lines.0.revenue_type_id', null);
});

it('AC-003: a new inactive or unknown sub-account is a 422, the one already on the invoice stays valid', function () {
    $type = revenueSubAccount();
    Sanctum::actingAs(invoiceUserWith(['create', 'update', 'view']));

    [$request, $payload] = classifiedInvoiceRequest(null);
    foreach ([revenueSubAccount(['is_active' => false])->id, 999999] as $bad) {
        $payload['lines'][0]['revenue_type_id'] = $bad;
        $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertUnprocessable()->assertJsonValidationErrors('lines.0.revenue_type_id');
    }

    $payload['lines'][0]['revenue_type_id'] = $type->id;
    $invoiceId = $this->postJson("/api/proforma-requests/{$request->id}/invoice", $payload)->assertCreated()->json('data.id');
    $type->update(['is_active' => false]);

    $this->putJson("/api/invoices/{$invoiceId}", $payload)->assertOk()->assertJsonPath('data.lines.0.revenue_type_id', $type->id);
});

it('AC-004: the draft exposes the proposal on every available line', function () {
    $type = revenueSubAccount();
    [$request] = classifiedInvoiceRequest($type->id);
    Sanctum::actingAs(invoiceUserWith(['create']));

    $lines = $this->getJson("/api/proforma-requests/{$request->id}/invoice-draft")->assertOk()->json('data.available_lines');

    $classified = collect($lines)->where('revenue_type_id', $type->id);
    expect($classified)->toHaveCount(1)->and($classified->first()['revenue_type']['id'])->toBe($type->id);
    expect(collect($lines)->whereNull('revenue_type_id'))->toHaveCount(1);
});

it('AC-005: PATCH changes only the line classification on an invoice with collections and logs it', function () {
    Sanctum::actingAs(invoiceUserWith(['create', 'update', 'collect', 'view']));
    [$data] = invoiceWithPlan('1200.00', 2);
    collectInstallment($data['installments'][0]['id'], $data['installments'][0]['amount'])->assertOk();
    $invoice = Invoice::query()->findOrFail($data['id']);
    $line = $invoice->lines()->firstOrFail();
    $type = revenueSubAccount();
    $totals = [$invoice->total_amount, $line->net_amount];

    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", ['revenue_type_id' => $type->id])
        ->assertOk()
        ->assertJsonPath('data.id', $line->id)
        ->assertJsonPath('data.revenue_type_id', $type->id)
        ->assertJsonPath('data.revenue_type.path', $type->chartAccount->parent->code.' '.$type->chartAccount->parent->name.' > '.$type->chartAccount->code.' '.$type->chartAccount->name);

    expect([$invoice->fresh()->total_amount, $line->fresh()->net_amount])->toBe($totals);
    expect(Activity::query()->where('event', 'line-revenue-type.updated')->where('subject_id', $invoice->id)->count())->toBe(1);

    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", ['revenue_type_id' => null])->assertOk()->assertJsonPath('data.revenue_type', null);
});

it('AC-005: a line of another invoice is 404, without invoices.update 403, an inactive sub-account 422', function () {
    $invoice = Invoice::factory()->create();
    $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id]);
    $foreign = InvoiceLine::factory()->create();
    $type = revenueSubAccount();
    [$viewer, $editor] = [invoiceUserWith(['view']), invoiceUserWith(['update'])];

    Sanctum::actingAs($viewer);
    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", ['revenue_type_id' => $type->id])->assertForbidden();

    Sanctum::actingAs($editor);
    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$foreign->id}/revenue-type", ['revenue_type_id' => $type->id])->assertNotFound();
    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", [])->assertUnprocessable();
    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", ['revenue_type_id' => revenueSubAccount(['is_active' => false])->id])
        ->assertUnprocessable()->assertJsonValidationErrors('revenue_type_id');

    expect($line->fresh()->revenue_type_id)->toBeNull();
});

it('AC-005: a registered invoice can be classified too', function () {
    $invoice = Invoice::factory()->asInvoice()->create();
    $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id]);
    Sanctum::actingAs(invoiceUserWith(['update']));

    $this->patchJson("/api/invoices/{$invoice->id}/lines/{$line->id}/revenue-type", ['revenue_type_id' => revenueSubAccount()->id])->assertOk();
});
