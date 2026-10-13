<?php

use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

const PURCHASE_INVOICE_BASE_ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

/** A registered invoice (type=invoice) created through the API. */
function registeredPurchaseInvoice(array $overrides = []): PurchaseInvoice
{
    return createPurchaseInvoice([...['document_type' => 'invoice', 'document_number' => 'FT-100'], ...$overrides]);
}

it('AC-005: the same supplier number in the same year is refused on an invoice, other year or proforma is fine', function () {
    $first = registeredPurchaseInvoice();
    $supplier = $first->supplier_id;
    $payload = fn (array $overrides) => purchaseInvoicePayload([...['supplier_id' => $supplier, 'company_id' => $first->company_id], ...$overrides]);

    $this->postJson('/api/purchase-invoices', $payload(['document_type' => 'invoice', 'document_number' => 'FT-100']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $this->postJson('/api/purchase-invoices', $payload(['document_type' => 'invoice', 'document_number' => 'FT-100', 'document_date' => '2027-01-10']))
        ->assertCreated()->assertJsonPath('data.document_number', 'FT-100');
    $this->postJson('/api/purchase-invoices', $payload(['document_type' => 'proforma', 'document_number' => 'FT-100']))
        ->assertCreated();
    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['document_type' => 'invoice', 'document_number' => 'FT-100']))
        ->assertCreated();

    expect(PurchaseInvoice::query()->where('document_year', 2026)->where('document_number', 'FT-100')->count())->toBe(2);
});

it('AC-005: registering a proforma through the details checks the number against the invoices of the year', function () {
    $first = registeredPurchaseInvoice();
    $proforma = createPurchaseInvoice(['supplier_id' => $first->supplier_id, 'company_id' => $first->company_id]);

    $this->patchJson("/api/purchase-invoices/{$proforma->id}/details", ['document_number' => 'FT-100'])
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');

    $this->patchJson("/api/purchase-invoices/{$proforma->id}/details", ['document_number' => 'FT-101'])->assertOk();
});

it('AC-006: PATCH details with a number turns a proforma into an invoice', function () {
    $proforma = createPurchaseInvoice();

    expect($proforma->document_year)->toBeNull();

    $this->patchJson("/api/purchase-invoices/{$proforma->id}/details", ['document_number' => 'FT-7', 'notes' => 'registered'])
        ->assertOk()
        ->assertJsonPath('data.document_type', 'invoice')
        ->assertJsonPath('data.document_number', 'FT-7')
        ->assertJsonPath('data.notes', 'registered')
        ->assertJsonPath('permissions.actions.update', false)
        ->assertJsonPath('permissions.actions.delete', false);

    expect($proforma->refresh()->document_year)->toBe(2026);
});

it('AC-006: PUT and DELETE on an invoice answer 409 and change nothing', function () {
    $invoice = registeredPurchaseInvoice();

    $this->putJson("/api/purchase-invoices/{$invoice->id}", purchaseInvoiceUpdatePayload($invoice, ['notes' => 'x']))
        ->assertStatus(409)->assertJsonPath('message', __('purchase_invoices.registered_locked'));
    $this->deleteJson("/api/purchase-invoices/{$invoice->id}")
        ->assertStatus(409)->assertJsonPath('message', __('purchase_invoices.registered_locked'));

    expect($invoice->refresh()->notes)->toBeNull()
        ->and(PurchaseInvoice::query()->count())->toBe(1);
});

it('AC-006: clearing or changing the number without downgrade is 403, notes alone are fine', function () {
    $invoice = registeredPurchaseInvoice();
    Sanctum::actingAs(purchaseInvoiceUserWith(PURCHASE_INVOICE_BASE_ABILITIES));

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => null])
        ->assertForbidden()->assertJsonPath('message', __('purchase_invoices.downgrade_forbidden'));
    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => 'FT-999'])->assertForbidden();
    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => 'FT-100', 'notes' => 'fine'])
        ->assertOk()->assertJsonPath('data.document_type', 'invoice');

    expect($invoice->refresh()->document_number)->toBe('FT-100')->and($invoice->notes)->toBe('fine');
});

it('AC-006: with downgrade the number is cleared, the document is a proforma again and the change is logged', function () {
    $invoice = registeredPurchaseInvoice();
    Sanctum::actingAs(purchaseInvoiceUserWith([...PURCHASE_INVOICE_BASE_ABILITIES, 'downgrade']));

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => null])
        ->assertOk()->assertJsonPath('data.document_type', 'proforma')->assertJsonPath('data.document_number', null)
        ->assertJsonPath('permissions.actions.update', true);

    $changes = Activity::query()->where('subject_id', $invoice->id)->where('event', 'updated')->latest('id')->firstOrFail()->properties;

    expect($changes['attributes'])->toHaveKeys(['document_number', 'document_type'])
        ->and($changes['old']['document_number'])->toBe('FT-100')
        ->and($changes['old']['document_type'])->toBe('invoice')
        ->and($invoice->refresh()->document_year)->toBeNull();

    $this->putJson("/api/purchase-invoices/{$invoice->id}", purchaseInvoiceUpdatePayload($invoice, ['notes' => 'editable again']))->assertOk();
});

it('AC-006: an actor with downgrade can change the number to another one, kept as invoice', function () {
    $invoice = registeredPurchaseInvoice();
    Sanctum::actingAs(purchaseInvoiceUserWith([...PURCHASE_INVOICE_BASE_ABILITIES, 'downgrade']));

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => 'FT-200'])
        ->assertOk()->assertJsonPath('data.document_type', 'invoice')->assertJsonPath('data.document_number', 'FT-200')
        ->assertJsonPath('permissions.actions.downgrade', true);
});

it('AC-006: details need the update ability', function () {
    $invoice = registeredPurchaseInvoice();
    Sanctum::actingAs(purchaseInvoiceUserWith(['view']));

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['notes' => 'x'])->assertForbidden();
});

it('refuses a number longer than the column', function () {
    $proforma = createPurchaseInvoice();

    $this->patchJson("/api/purchase-invoices/{$proforma->id}/details", ['document_number' => str_repeat('9', 31)])
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');
});
