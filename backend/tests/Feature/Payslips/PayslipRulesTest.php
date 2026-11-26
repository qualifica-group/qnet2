<?php

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// Spec 0431, AC-001 (permission part) / AC-008 — the payslip in the payable cycle (D-7, D-11).
uses(RefreshDatabase::class);

const PAYSLIP_PERMISSIONS = [
    'purchase-invoices.viewAny', 'purchase-invoices.view', 'purchase-invoices.create', 'purchase-invoices.update',
    'purchase-invoices.delete', 'purchase-invoices.pay', 'purchase-invoices.downgrade',
];

/** A payslip document with one line and one installment of 1000.00, plus an actor holding the cycle permissions. */
function payslipDocument(): PurchaseInvoice
{
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view', 'update'], PAYSLIP_PERMISSIONS));

    $document = mcPayable([['due' => 10, 'amount' => '1000.00']], [
        'document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null, 'document_date' => '2026-03-31',
    ]);
    mcPayableLine(null, '1000.00', invoice: $document);

    return $document;
}

it('AC-001: permissions:sync creates purchase-invoices.import', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'purchase-invoices.import')->exists())->toBeTrue();
});

it('AC-008: a number on a payslip is refused and the type never changes', function () {
    $document = payslipDocument();

    $this->patchJson("/api/purchase-invoices/{$document->id}/details", ['document_number' => 'F-1'])
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');
    $this->patchJson("/api/purchase-invoices/{$document->id}/details", ['document_number' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('document_number');

    expect($document->refresh()->document_type)->toBe(PurchaseInvoiceType::Payslip)
        ->and($document->document_number)->toBeNull();
});

it('AC-008: header and lines are not editable, the notes are', function () {
    $document = payslipDocument();
    $before = $document->only(['document_date', 'total_amount']);

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['document_date' => '2026-04-30']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_type');
    $this->patchJson("/api/purchase-invoices/{$document->id}", ['document_date' => '2026-04-30'])->assertUnprocessable();
    $this->patchJson("/api/purchase-invoices/{$document->id}", ['lines' => []])->assertUnprocessable();

    expect($document->refresh()->only(['document_date', 'total_amount']))->toEqual($before);

    $this->patchJson("/api/purchase-invoices/{$document->id}/details", ['notes' => 'Ottobre'])
        ->assertOk()->assertJsonPath('data.notes', 'Ottobre')->assertJsonPath('data.document_type', 'payslip');
});

it('AC-008: the detail reports no update and keeps the details action for the notes', function () {
    $document = payslipDocument();

    $response = $this->getJson("/api/purchase-invoices/{$document->id}")->assertOk();

    expect($response->json('permissions.actions.update'))->toBeFalse()
        ->and($response->json('permissions.actions.details'))->toBeTrue();
});

it('AC-008: payment, bulk payment and deletion without payments work as for other documents', function () {
    $paid = payslipDocument();
    $installment = $paid->installments()->firstOrFail();

    payInstallment($installment->id, '1000.00')->assertOk();
    expect((float) $installment->refresh()->paid_amount)->toBe(1000.0);

    $bulk = mcPayable([['due' => 10, 'amount' => '500.00']], ['document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null]);
    $this->postJson('/api/purchase-invoice-installments/payments', [
        'paid_at' => '2026-04-20',
        'items' => [['installment_id' => $bulk->installments()->value('id'), 'paid_amount' => '500.00']],
    ])->assertOk()->assertJsonPath('data.paid_count', 1);

    $deletable = mcPayable([['due' => 10, 'amount' => '300.00']], ['document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null]);
    $this->deleteJson("/api/purchase-invoices/{$deletable->id}")->assertNoContent();
    expect(PurchaseInvoice::query()->whereKey($deletable->id)->exists())->toBeFalse();

    $this->deleteJson("/api/purchase-invoices/{$paid->id}")->assertStatus(409);
});

it('AC-008: the grid row is read-only, offers no edit and the type filter lists payslip', function () {
    $document = payslipDocument();

    $row = collect($this->postJson('/api/tables/purchase-invoices/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items'))->firstWhere('id', $document->id);
    expect($row['editable'])->toBeFalse()
        ->and($row['actions'])->not->toContain('update')->not->toContain('details');

    $this->patchJson("/api/tables/purchase-invoices/rows/{$document->id}", ['column' => 'document_date', 'value' => '2026-04-30'])->assertForbidden();

    $columns = collect($this->getJson('/api/tables/purchase-invoices/columns')->assertOk()->json('data.columns'));
    expect($columns->firstWhere('id', 'document_type')['options'])->toContain('payslip');
});

it('AC-008: a payslip cannot be created through the public POST', function () {
    Sanctum::actingAs(purchaseInvoiceUserWith(['viewAny', 'view', 'create']));

    $this->postJson('/api/purchase-invoices', purchaseInvoicePayload(['document_type' => 'payslip']))
        ->assertUnprocessable()->assertJsonValidationErrors('document_type');
});
