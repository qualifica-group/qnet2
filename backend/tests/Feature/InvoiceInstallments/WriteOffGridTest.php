<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// Spec 0361, AC-011 — write-off columns and status on both installment grids.

function writeOffGridIds(string $domain, array $filterModel): array
{
    return collect(test()->postJson("/api/tables/{$domain}/rows", ['startRow' => 0, 'endRow' => 50, 'filterModel' => $filterModel])->assertOk()->json('items'))
        ->pluck('id')->all();
}

it('AC-011: the active grid exposes and filters the write-off and counts it as paid, not residual', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.view']));
    $invoice = Invoice::factory()->create();
    $open = InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => '100.00']);
    $partial = InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 2, 'amount' => '100.00', 'collected_amount' => '99.80', 'collected_at' => '2026-05-01', 'write_off_amount' => '0.20', 'write_off_reason' => 'rounding']);
    $zero = InvoiceInstallment::factory()->writtenOff('100.00', 'bad_debt')->create(['invoice_id' => $invoice->id, 'sequence' => 3, 'amount' => '100.00', 'collected_amount' => '0.00', 'collected_at' => '2026-05-02']);

    $columns = collect($this->getJson('/api/tables/invoice-installments/columns')->assertOk()->json('data.columns'))->keyBy('id');
    $rows = collect($this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 50])->json('items'))->keyBy('id');

    expect($columns['write_off_amount'])->toMatchArray(['type' => 'number', 'filterType' => 'number', 'sortable' => true])
        ->and($columns['write_off_reason']['filterType'])->toBe('set')
        ->and($columns['write_off_reason']['options'])->toBe(['rounding', 'bad_debt'])
        ->and($rows[$partial->id])->toMatchArray(['status' => 'paid', 'write_off_amount' => '0.20', 'write_off_reason' => 'rounding', 'residual_amount' => '0.00'])
        ->and($rows[$open->id]['write_off_amount'])->toBeNull()
        ->and(writeOffGridIds('invoice-installments', ['status' => ['filterType' => 'set', 'values' => ['paid']]]))->toEqualCanonicalizing([$partial->id, $zero->id])
        ->and(writeOffGridIds('invoice-installments', ['status' => ['filterType' => 'set', 'values' => ['unpaid']]]))->toBe([$open->id])
        ->and(writeOffGridIds('invoice-installments', ['write_off_reason' => ['filterType' => 'set', 'values' => ['bad_debt']]]))->toBe([$zero->id])
        ->and(writeOffGridIds('invoice-installments', ['write_off_amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 50]]))->toBe([$zero->id]);
});

it('AC-011: the payable grid exposes and filters the write-off and counts it as paid, not residual', function () {
    Sanctum::actingAs(purchaseInstallmentUserWith(['viewAny', 'view'], ['purchase-invoices.view']));
    $document = PurchaseInvoice::factory()->create();
    $open = PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $document->id, 'sequence' => 1, 'amount' => '100.00']);
    $partial = PurchaseInvoiceInstallment::factory()->create(['purchase_invoice_id' => $document->id, 'sequence' => 2, 'amount' => '100.00', 'paid_amount' => '99.50', 'paid_at' => '2026-05-01', 'write_off_amount' => '0.50', 'write_off_reason' => 'rounding']);
    $zero = PurchaseInvoiceInstallment::factory()->writtenOff('100.00', 'bad_debt')->create(['purchase_invoice_id' => $document->id, 'sequence' => 3, 'amount' => '100.00', 'paid_amount' => '0.00', 'paid_at' => '2026-05-02']);

    $columns = collect($this->getJson('/api/tables/purchase-invoice-installments/columns')->assertOk()->json('data.columns'))->keyBy('id');
    $rows = collect($this->postJson('/api/tables/purchase-invoice-installments/rows', ['startRow' => 0, 'endRow' => 50])->json('items'))->keyBy('id');

    expect($columns['write_off_amount']['filterType'])->toBe('number')
        ->and($columns['write_off_reason']['options'])->toBe(['rounding', 'bad_debt'])
        ->and($rows[$partial->id])->toMatchArray(['status' => 'paid', 'write_off_amount' => '0.50', 'write_off_reason' => 'rounding', 'residual_amount' => '0.00'])
        ->and(writeOffGridIds('purchase-invoice-installments', ['status' => ['filterType' => 'set', 'values' => ['paid']]]))->toEqualCanonicalizing([$partial->id, $zero->id])
        ->and(writeOffGridIds('purchase-invoice-installments', ['status' => ['filterType' => 'set', 'values' => ['unpaid']]]))->toBe([$open->id])
        ->and(writeOffGridIds('purchase-invoice-installments', ['write_off_reason' => ['filterType' => 'set', 'values' => ['bad_debt']]]))->toBe([$zero->id])
        ->and(writeOffGridIds('purchase-invoice-installments', ['write_off_amount' => ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 10]]))->toBe([$zero->id]);
});
