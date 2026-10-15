<?php

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * An APPROVED settlement linked to an invoice whose installments are [days from today, paid].
 *
 * @param  array<int, array{int, bool}>  $installments
 */
function approvedWithInstallments(array $installments): int
{
    $invoice = PurchaseInvoice::factory()->create();

    foreach ($installments as $sequence => [$days, $paid]) {
        PurchaseInvoiceInstallment::factory()->create([
            'purchase_invoice_id' => $invoice->id,
            'sequence' => $sequence + 1,
            'amount' => '100.00',
            'paid_amount' => $paid ? '100.00' : null,
            'due_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    return amountedSettlement('approved', ['purchase_invoice_id' => $invoice->id, 'document_number' => 'FA-'.$invoice->id])->id;
}

it('exposes document_number and payment_status (null unless a purchase invoice is linked) and filters by them', function () {
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'view']));
    $notDue = approvedWithInstallments([[10, false]]);
    $paid = approvedWithInstallments([[-30, true], [-10, true]]);
    $overdue = approvedWithInstallments([[-5, false]]);
    $serious = approvedWithInstallments([[-30, false]]);
    $issued = amountedSettlement()->id;

    $rows = collect(settlementTableRows('commission-settlements')['items'])->keyBy('id');

    expect($rows[$notDue]['payment_status'])->toBe('not_due')
        ->and($rows[$paid]['payment_status'])->toBe('paid')
        ->and($rows[$overdue]['payment_status'])->toBe('overdue')
        ->and($rows[$serious]['payment_status'])->toBe('seriously_overdue')
        ->and($rows[$issued]['payment_status'])->toBeNull()
        ->and($rows[$notDue]['document_number'])->toStartWith('FA-');

    $set = fn (array $values): array => ['filterType' => 'set', 'values' => $values];

    expect(settlementTableIds('commission-settlements', ['filterModel' => ['payment_status' => $set(['overdue', 'seriously_overdue'])]]))->toEqualCanonicalizing([$overdue, $serious])
        ->and(settlementTableIds('commission-settlements', ['filterModel' => ['payment_status' => $set(['paid'])]]))->toBe([$paid])
        ->and(settlementTableIds('commission-settlements', ['filterModel' => ['document_number' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'FA-']]]))->toHaveCount(4);
});

it('the row abilities of the document actions are permission AND state', function () {
    Sanctum::actingAs(commissionSettlementUserWith(['viewAny', 'receiveDocument', 'approve']));
    $issued = amountedSettlement()->id;
    $received = amountedSettlement('document_received')->id;
    $approved = amountedSettlement('approved')->id;

    $rows = collect(settlementTableRows('commission-settlements')['items'])->keyBy('id');
    $pick = fn (int $id): array => array_intersect_key($rows[$id]['abilities'], array_flip(['receive_document', 'remove_document', 'approve']));

    expect($pick($issued))->toBe(['receive_document' => true, 'remove_document' => false, 'approve' => false])
        ->and($pick($received))->toBe(['receive_document' => false, 'remove_document' => true, 'approve' => true])
        ->and($pick($approved))->toBe(['receive_document' => false, 'remove_document' => false, 'approve' => false]);
});
