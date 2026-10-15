<?php

declare(strict_types=1);

use App\Enums\PurchaseInvoiceType;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0262: the passive invoice saves one header field at a time through a
// partial PATCH (stored values merged, lines and ODA links included), the
// grids edit their cells through the same FormRequests + services, and the
// edit/details row actions are gone.

uses(RefreshDatabase::class);

function purchaseInvoicingCellPatch(string $resource, int $id, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/{$resource}/rows/{$id}", ['column' => $column, 'value' => $value]);
}

it('AC-001: patches the notes alone and keeps lines and schedule', function () {
    $document = createPurchaseInvoice();
    $lines = $document->lines()->count();

    $this->patchJson("/api/purchase-invoices/{$document->id}", ['notes' => 'Ritirato in sede'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Ritirato in sede')
        ->assertJsonPath('data.total_amount', '317.35')
        ->assertJsonCount($lines, 'data.lines')
        ->assertJsonCount(2, 'data.installments');
});

it('AC-001: a new payment method replans the schedule; a deactivated stored one does not block', function () {
    $document = createPurchaseInvoice();
    $single = PaymentMethod::factory()->create(['installments_count' => 1, 'payment_days' => 0, 'days_between_installments' => 0]);

    $this->patchJson("/api/purchase-invoices/{$document->id}", ['payment_method_id' => $single->id])
        ->assertOk()
        ->assertJsonCount(1, 'data.installments');

    $single->update(['is_active' => false]);
    $this->patchJson("/api/purchase-invoices/{$document->id}", ['notes' => 'Ok'])->assertOk();
});

it('AC-002: a registered invoice refuses the header patch', function () {
    $document = createPurchaseInvoice();
    $document->update(['document_type' => PurchaseInvoiceType::Invoice, 'document_number' => 'F-12']);

    $this->patchJson("/api/purchase-invoices/{$document->id}", ['notes' => 'Late'])->assertStatus(409);
});

it('AC-003: the document date is a grid cell on a proforma, and the edit/details actions are gone', function () {
    $document = createPurchaseInvoice();

    $columns = collect($this->getJson('/api/tables/purchase-invoices/columns')->assertOk()->json('data.columns'));
    expect($columns->where('editable', true)->pluck('id')->all())->toBe(['document_date']);

    purchaseInvoicingCellPatch('purchase-invoices', $document->id, 'document_date', '2026-03-20')
        ->assertOk()->assertJsonPath('data.document_date', '2026-03-20');

    $row = collect($this->postJson('/api/tables/purchase-invoices/rows', ['startRow' => 0, 'endRow' => 10])->json('items'))->first();
    expect($row['actions'])->not->toContain('update')->not->toContain('details');
});

it('AC-003: a registered invoice is a read-only row', function () {
    $document = createPurchaseInvoice();
    $document->update(['document_type' => PurchaseInvoiceType::Invoice, 'document_number' => 'F-12']);

    $row = collect($this->postJson('/api/tables/purchase-invoices/rows', ['startRow' => 0, 'endRow' => 10])->json('items'))->first();
    expect($row['editable'])->toBeFalse();

    purchaseInvoicingCellPatch('purchase-invoices', $document->id, 'document_date', '2026-03-20')->assertForbidden();
});

it('AC-004: edits due date and method of an unpaid installment in the cell, not of a paid one', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view', 'update']));
    $document = purchaseDocumentWithPlan();
    [$first, $second] = $document->installments()->orderBy('sequence')->get()->all();
    $second->update(['paid_amount' => '100.00', 'paid_at' => '2026-05-01']);

    $columns = collect($this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data.columns'));
    expect($columns->where('editable', true)->pluck('id')->sort()->values()->all())->toBe(['due_date', 'payment_method_code']);

    purchaseInvoicingCellPatch('purchase-invoice-installments', $first->id, 'due_date', '2026-04-30')
        ->assertOk()->assertJsonPath('data.due_date', '2026-04-30');
    purchaseInvoicingCellPatch('purchase-invoice-installments', $first->id, 'due_date', '2026-01-01')->assertUnprocessable();
    purchaseInvoicingCellPatch('purchase-invoice-installments', $second->id, 'due_date', '2026-05-30')->assertForbidden();

    $actions = collect($this->postJson('/api/tables/purchase-invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->json('items'))
        ->pluck('actions')->flatten()->unique()->all();
    expect($actions)->not->toContain('edit');
});

it('keeps the full PUT working for the lines editor', function () {
    $document = createPurchaseInvoice();

    $this->putJson("/api/purchase-invoices/{$document->id}", purchaseInvoiceUpdatePayload($document, ['notes' => 'Via PUT']))
        ->assertOk()->assertJsonPath('data.notes', 'Via PUT');

    expect(PurchaseInvoice::query()->find($document->id)->notes)->toBe('Via PUT');
});
