<?php

use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Registry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

const BULK_COLLECTION_URL = '/api/invoice-installments/collections';

beforeEach(function () {
    $this->customer = Registry::factory()->create();
    Sanctum::actingAs(installmentUserWith([], ['invoices.collect']));
});

function bulkInvoice(Registry $customer): Invoice
{
    return installmentInvoice($customer, null, null, ['document_date' => '2026-09-01']);
}

/**
 * @return array<int, array{sequence: int, due_date: string, amount: string, collected_amount: ?string, collected_at: ?string}>
 */
function bulkPlan(Invoice $invoice): array
{
    return $invoice->installments()->orderBy('sequence')->get()
        ->map(fn (InvoiceInstallment $row): array => [
            'sequence' => $row->sequence,
            'due_date' => $row->due_date->toDateString(),
            'amount' => (string) $row->amount,
            'collected_amount' => $row->collected_amount === null ? null : (string) $row->collected_amount,
            'collected_at' => $row->collected_at?->toDateString(),
        ])->all();
}

it('AC-001: collects two installments of one customer, the partial one leaving a residual on its original due date', function () {
    $first = installmentOf(bulkInvoice($this->customer), ['due_date' => '2026-10-31', 'amount' => '1000.00']);
    $second = installmentOf(bulkInvoice($this->customer), ['due_date' => '2026-11-30', 'amount' => '500.00']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $first->id, 'collected_amount' => 600],
            ['installment_id' => $second->id, 'collected_amount' => '500.00'],
        ],
    ])->assertOk()->assertJsonPath('data', ['collected_count' => 2, 'residual_count' => 1]);

    expect(bulkPlan($first->invoice))->toBe([
        ['sequence' => 1, 'due_date' => '2026-10-31', 'amount' => '600.00', 'collected_amount' => '600.00', 'collected_at' => '2026-10-08'],
        ['sequence' => 2, 'due_date' => '2026-10-31', 'amount' => '400.00', 'collected_amount' => null, 'collected_at' => null],
    ])->and(bulkPlan($second->invoice))->toBe([
        ['sequence' => 1, 'due_date' => '2026-11-30', 'amount' => '500.00', 'collected_amount' => '500.00', 'collected_at' => '2026-10-08'],
    ]);
});

it('AC-002/AC-003: residuals of two installments of the same invoice keep their own due dates and contiguous sequences', function () {
    $invoice = bulkInvoice($this->customer);
    $first = installmentOf($invoice, ['due_date' => '2026-10-31', 'amount' => '300.00']);
    $second = installmentOf($invoice, ['due_date' => '2026-11-30', 'amount' => '300.00']);
    installmentOf($invoice, ['due_date' => '2026-12-31', 'amount' => '300.00']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $first->id, 'collected_amount' => 100],
            ['installment_id' => $second->id, 'collected_amount' => 250],
        ],
    ])->assertOk()->assertJsonPath('data.residual_count', 2);

    expect(bulkPlan($invoice))->toBe([
        ['sequence' => 1, 'due_date' => '2026-10-31', 'amount' => '100.00', 'collected_amount' => '100.00', 'collected_at' => '2026-10-08'],
        ['sequence' => 2, 'due_date' => '2026-10-31', 'amount' => '200.00', 'collected_amount' => null, 'collected_at' => null],
        ['sequence' => 3, 'due_date' => '2026-11-30', 'amount' => '250.00', 'collected_amount' => '250.00', 'collected_at' => '2026-10-08'],
        ['sequence' => 4, 'due_date' => '2026-11-30', 'amount' => '50.00', 'collected_amount' => null, 'collected_at' => null],
        ['sequence' => 5, 'due_date' => '2026-12-31', 'amount' => '300.00', 'collected_amount' => null, 'collected_at' => null],
    ]);
});

it('AC-004: an amount above the installment is a 422 on that row and nothing changes', function () {
    $first = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);
    $second = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);
    $before = [bulkPlan($first->invoice), bulkPlan($second->invoice)];

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $first->id, 'collected_amount' => 50],
            ['installment_id' => $second->id, 'collected_amount' => 100.01],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.collected_amount']);

    expect([bulkPlan($first->invoice), bulkPlan($second->invoice)])->toBe($before);
});

it('AC-005: an already collected installment is a 422 on that row and nothing changes', function () {
    $open = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);
    $collected = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00', 'collected_amount' => '100.00', 'collected_at' => '2026-10-01']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $open->id, 'collected_amount' => 100],
            ['installment_id' => $collected->id, 'collected_amount' => 100],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.1.installment_id']);

    expect($open->fresh()->collected_amount)->toBeNull();
});

it('AC-006: installments of different customers are a 422 on items', function () {
    $mine = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);
    $other = installmentOf(bulkInvoice(Registry::factory()->create()), ['amount' => '100.00']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $mine->id, 'collected_amount' => 100],
            ['installment_id' => $other->id, 'collected_amount' => 100],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items']);

    expect($mine->fresh()->collected_amount)->toBeNull();
});

it('AC-007: a user without invoices.collect gets 403', function () {
    Sanctum::actingAs(installmentUserWith(['view', 'update']));
    $installment = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [['installment_id' => $installment->id, 'collected_amount' => 100]],
    ])->assertForbidden();

    expect($installment->fresh()->collected_amount)->toBeNull();
});

it('AC-008: clearing a partial bulk collection restores the previous plan', function () {
    $invoice = bulkInvoice($this->customer);
    $first = installmentOf($invoice, ['due_date' => '2026-10-31', 'amount' => '300.00']);
    installmentOf($invoice, ['due_date' => '2026-11-30', 'amount' => '300.00']);
    $before = bulkPlan($invoice);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [['installment_id' => $first->id, 'collected_amount' => 100]],
    ])->assertOk();

    $this->deleteJson("/api/invoice-installments/{$first->id}/collection")->assertOk();

    expect(bulkPlan($invoice))->toBe($before);
});

it('validates the collection date and the items', function (array $body, array $errors) {
    $this->postJson(BULK_COLLECTION_URL, $body)->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'nothing' => [[], ['collected_at', 'items']],
    'empty items' => [['collected_at' => '2026-10-08', 'items' => []], ['items']],
    'zero amount and unknown id' => [
        ['collected_at' => '2026-10-08', 'items' => [['installment_id' => 999999, 'collected_amount' => 0]]],
        ['items.0.installment_id', 'items.0.collected_amount'],
    ],
]);

it('rejects a duplicated installment', function () {
    $installment = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);

    $this->postJson(BULK_COLLECTION_URL, [
        'collected_at' => '2026-10-08',
        'items' => [
            ['installment_id' => $installment->id, 'collected_amount' => 10],
            ['installment_id' => $installment->id, 'collected_amount' => 10],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.installment_id']);
});

it('D-10: the installment rows carry the customer id', function () {
    Sanctum::actingAs(installmentUserWith(['viewAny', 'view'], ['invoices.collect']));
    $installment = installmentOf(bulkInvoice($this->customer), ['amount' => '100.00']);

    $rows = $this->postJson('/api/tables/invoice-installments/rows', ['startRow' => 0, 'endRow' => 10])->assertOk()->json('items');

    expect(collect($rows)->firstWhere('id', $installment->id)['customer_id'])->toBe($this->customer->id);
});
