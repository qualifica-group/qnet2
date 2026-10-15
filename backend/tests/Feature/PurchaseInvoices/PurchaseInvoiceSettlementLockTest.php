<?php

use App\Models\CommissionSettlement;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

// Spec 0267, D-5 (AC-009): a payable document born from a settlement is owned by it.

/** An invoice linked to an approved settlement, handled by a user holding every ability. */
function settlementInvoice(): PurchaseInvoice
{
    $invoice = createPurchaseInvoice(['document_type' => 'invoice', 'document_number' => 'FT-900']);

    foreach (['purchase-invoices.pay', 'purchase-invoice-installments.view', 'purchase-invoice-installments.update'] as $name) {
        Permission::findOrCreate($name, 'web');
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    auth()->user()->givePermissionTo(['purchase-invoices.pay', 'purchase-invoice-installments.view', 'purchase-invoice-installments.update']);

    CommissionSettlement::factory()->approved()->create(['purchase_invoice_id' => $invoice->id]);

    return $invoice;
}

it('AC-009: PUT, PATCH and DELETE answer 409 settlement_locked and change nothing', function () {
    $invoice = settlementInvoice();
    $message = __('purchase_invoices.settlement_locked');

    $this->putJson("/api/purchase-invoices/{$invoice->id}", purchaseInvoiceUpdatePayload($invoice, ['notes' => 'x']))
        ->assertStatus(409)->assertJsonPath('message', $message);
    $this->patchJson("/api/purchase-invoices/{$invoice->id}", ['notes' => 'x'])
        ->assertStatus(409)->assertJsonPath('message', $message);
    $this->deleteJson("/api/purchase-invoices/{$invoice->id}")
        ->assertStatus(409)->assertJsonPath('message', $message);

    expect(PurchaseInvoice::query()->whereKey($invoice->id)->exists())->toBeTrue()
        ->and($invoice->refresh()->notes)->toBeNull();
});

it('AC-009: a number change is refused even with the downgrade permission, notes stay editable', function () {
    $invoice = settlementInvoice();

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => 'FT-901'])
        ->assertStatus(409)->assertJsonPath('message', __('purchase_invoices.settlement_locked'));
    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['document_number' => null])
        ->assertStatus(409);

    $this->patchJson("/api/purchase-invoices/{$invoice->id}/details", ['notes' => 'hello'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'hello')
        ->assertJsonPath('data.document_number', 'FT-900');
});

it('AC-009: the resource links the settlement and the abilities are off', function () {
    $invoice = settlementInvoice();
    $settlement = $invoice->commissionSettlement()->firstOrFail();

    $this->getJson("/api/purchase-invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.commission_settlement.id', $settlement->id)
        ->assertJsonPath('data.commission_settlement.code', $settlement->code())
        ->assertJsonPath('permissions.actions.update', false)
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.downgrade', false)
        ->assertJsonPath('permissions.actions.details', true)
        ->assertJsonPath('permissions.fields.document_number.editable', false);
});

it('AC-009: the row delete action is not offered in the grid', function () {
    $free = createPurchaseInvoice();
    $linked = createPurchaseInvoice();
    auth()->user()->givePermissionTo(Permission::findOrCreate('purchase-invoices.viewActivity', 'web'));
    CommissionSettlement::factory()->approved()->create(['purchase_invoice_id' => $linked->id]);

    $rows = collect($this->postJson('/api/tables/purchase-invoices/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))->keyBy('id');

    expect($rows[$free->id]['actions'])->toContain('delete')
        ->and($rows[$linked->id]['actions'])->not->toContain('delete');
});

it('AC-009: payments and the due date of an unpaid installment stay allowed', function () {
    $invoice = settlementInvoice();
    $rows = $invoice->installments()->orderBy('sequence')->get();

    $this->patchJson("/api/purchase-invoice-installments/{$rows[1]->id}", ['due_date' => '2026-12-31'])->assertOk();
    $this->putJson("/api/purchase-invoice-installments/{$rows[0]->id}/payment", ['paid_amount' => (string) $rows[0]->amount, 'paid_at' => '2026-04-20'])->assertOk();

    expect($rows[1]->refresh()->due_date->toDateString())->toBe('2026-12-31')
        ->and($rows[0]->refresh()->paid_amount)->not->toBeNull();
});

it('AC-009: an invoice without a settlement is unaffected by the guard', function () {
    $proforma = createPurchaseInvoice();

    $this->patchJson("/api/purchase-invoices/{$proforma->id}", ['notes' => 'x'])->assertOk();
    $this->deleteJson("/api/purchase-invoices/{$proforma->id}")->assertNoContent();
});
