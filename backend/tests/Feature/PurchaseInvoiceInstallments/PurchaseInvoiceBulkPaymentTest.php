<?php

use App\Models\Company;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const BULK_PAYMENT_URL = '/api/purchase-invoice-installments/payments';

beforeEach(function () {
    $this->supplier = Registry::factory()->supplier()->create();
    Sanctum::actingAs(purchaseInstallmentUserWith([], ['purchase-invoices.pay']));
});

/**
 * @return array<int, array{sequence: int, due_date: string, amount: string, paid_amount: ?string, paid_at: ?string}>
 */
function bulkPurchasePlan(PurchaseInvoice $document): array
{
    return $document->installments()->get()
        ->map(fn (PurchaseInvoiceInstallment $row): array => [
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'amount' => (string) $row->amount,
            'paid_amount' => $row->paid_amount === null ? null : (string) $row->paid_amount,
            'paid_at' => $row->paid_at?->toDateString(),
        ])->all();
}

it('AC-010: pays two installments of one supplier, the partial one leaving a residual on its original due date', function () {
    $first = purchaseInstallmentOf(purchaseDocument($this->supplier), ['due_date' => '2026-10-31', 'amount' => '1000.00']);
    $second = purchaseInstallmentOf(purchaseDocument($this->supplier), ['due_date' => '2026-11-30', 'amount' => '500.00']);

    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $first->id, 'paid_amount' => 600],
            ['installment_id' => $second->id, 'paid_amount' => '500.00'],
        ],
    ])->assertOk()->assertJsonPath('data', ['paid_count' => 2, 'residual_count' => 1]);

    expect(bulkPurchasePlan($first->purchaseInvoice))->toBe([
        ['sequence' => 1, 'due_date' => '2026-10-31', 'amount' => '600.00', 'paid_amount' => '600.00', 'paid_at' => '2026-10-08'],
        ['sequence' => 2, 'due_date' => '2026-10-31', 'amount' => '400.00', 'paid_amount' => null, 'paid_at' => null],
    ])->and(bulkPurchasePlan($second->purchaseInvoice))->toBe([
        ['sequence' => 1, 'due_date' => '2026-11-30', 'amount' => '500.00', 'paid_amount' => '500.00', 'paid_at' => '2026-10-08'],
    ]);
});

it('AC-010: two installments of the same document each keep their own residual and contiguous sequences', function () {
    $document = purchaseDocument($this->supplier);
    $first = purchaseInstallmentOf($document, ['due_date' => '2026-10-31', 'amount' => '300.00']);
    $second = purchaseInstallmentOf($document, ['due_date' => '2026-11-30', 'amount' => '200.00']);

    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08',
        'items' => [['installment_id' => $first->id, 'paid_amount' => 100], ['installment_id' => $second->id, 'paid_amount' => 50]],
    ])->assertOk()->assertJsonPath('data.residual_count', 2);

    expect(array_column(bulkPurchasePlan($document), 'sequence'))->toBe([1, 2, 3, 4])
        ->and(array_column(bulkPurchasePlan($document), 'due_date'))->toBe(['2026-10-31', '2026-10-31', '2026-11-30', '2026-11-30']);
});

it('AC-010: two suppliers are a 422 on items and nothing is paid', function () {
    $mine = purchaseInstallmentOf(purchaseDocument($this->supplier), ['amount' => '100.00']);
    $other = purchaseInstallmentOf(purchaseDocument(), ['amount' => '100.00']);

    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08',
        'items' => [['installment_id' => $mine->id, 'paid_amount' => 100], ['installment_id' => $other->id, 'paid_amount' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items' => 'The installments must belong to the same supplier.']);

    expect(PurchaseInvoiceInstallment::query()->whereNotNull('paid_amount')->count())->toBe(0);
});

it('AC-010: a paid installment or an excess amount is a 422 keyed by item index, all or nothing', function () {
    $document = purchaseDocument($this->supplier);
    $ok = purchaseInstallmentOf($document, ['amount' => '100.00']);
    $paid = purchaseInstallmentOf($document, ['amount' => '100.00', 'paid_amount' => '100.00', 'paid_at' => '2026-10-01']);
    $excess = purchaseInstallmentOf($document, ['amount' => '100.00']);

    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $ok->id, 'paid_amount' => 100],
            ['installment_id' => $paid->id, 'paid_amount' => 100],
            ['installment_id' => $excess->id, 'paid_amount' => 100.01],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors([
        'items.1.installment_id' => 'This installment is already paid.',
        'items.2.paid_amount' => 'The paid amount cannot exceed the installment amount.',
    ]);

    expect($ok->fresh()->paid_amount)->toBeNull();
});

it('AC-009: an account of another company than one of the documents is a 422, nothing is paid', function () {
    $installment = purchaseInstallmentOf(purchaseDocument($this->supplier), ['amount' => '100.00']);
    $foreign = FinancialAccount::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08', 'financial_account_id' => $foreign->id,
        'items' => [['installment_id' => $installment->id, 'paid_amount' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['financial_account_id' => 'The account must belong to the document company.']);

    expect($installment->fresh()->paid_amount)->toBeNull();

    $own = FinancialAccount::factory()->create(['company_id' => $installment->purchaseInvoice->company_id]);
    $this->postJson(BULK_PAYMENT_URL, [
        'paid_at' => '2026-10-08', 'financial_account_id' => $own->id,
        'items' => [['installment_id' => $installment->id, 'paid_amount' => 100]],
    ])->assertOk();

    expect($installment->fresh()->financial_account_id)->toBe($own->id);
});

it('AC-009: without purchase-invoices.pay the bulk payment is a 403; malformed bodies are 422', function () {
    $installment = purchaseInstallmentOf(purchaseDocument($this->supplier), ['amount' => '100.00']);
    $body = ['paid_at' => '2026-10-08', 'items' => [['installment_id' => $installment->id, 'paid_amount' => 100]]];

    $this->postJson(BULK_PAYMENT_URL, [...$body, 'items' => []])->assertUnprocessable();
    $this->postJson(BULK_PAYMENT_URL, ['paid_at' => '2026-10-08', 'items' => [['installment_id' => 999999, 'paid_amount' => 1]]])->assertUnprocessable();
    $this->postJson(BULK_PAYMENT_URL, ['paid_at' => '2026-10-08', 'items' => [['installment_id' => $installment->id, 'paid_amount' => 1], ['installment_id' => $installment->id, 'paid_amount' => 1]]])->assertUnprocessable();

    Sanctum::actingAs(purchaseInstallmentUserWith(['view', 'update']));
    $this->postJson(BULK_PAYMENT_URL, $body)->assertForbidden();

    expect($installment->fresh()->paid_amount)->toBeNull();
});
