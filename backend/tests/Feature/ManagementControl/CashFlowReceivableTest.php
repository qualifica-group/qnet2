<?php

use App\Enums\InvoiceType;
use App\Enums\PurchaseInvoiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0270, AC-003 / AC-005 — the receivable side, the horizon and the balance.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-003: the overdue installments are the backlog, the months follow the due date, the rest is beyond the horizon', function () {
    mcReceivable([
        ['due' => -1, 'amount' => '10.00'], ['due' => -40, 'amount' => '20.00'], ['due' => 0, 'amount' => '30.00'],
        ['due' => mcMonthDue(2), 'amount' => '40.00'], ['due' => mcMonthDue(5), 'amount' => '50.00'], ['due' => mcMonthDue(8), 'amount' => '60.00'],
        ['due' => -3, 'amount' => '70.00', 'collected' => '70.00'],
    ]);

    $data = mcGet('cash-flow')->assertOk()->json('data');

    expect($data['backlog']['receivable'])->toEqual(30)
        ->and(array_column($data['months'], 'month'))->toBe(array_map(mcMonth(...), range(0, 5)))
        ->and(array_column($data['months'], 'receivable'))->toEqual([30, 0, 40, 0, 0, 50])
        ->and($data['beyond']['receivable'])->toEqual(60);

    $short = mcGet('cash-flow', ['horizon_months' => 3])->assertOk()->json('data');

    expect($short['months'])->toHaveCount(3)
        ->and($short['beyond']['receivable'])->toEqual(110);
});

it('AC-003: a partially collected installment counts only its residual', function () {
    mcReceivable([['due' => 5, 'amount' => '100.00', 'collected' => '40.00']]);

    $data = mcGet('cash-flow')->assertOk()->json('data');

    expect(array_sum(array_column($data['months'], 'receivable')) + $data['beyond']['receivable'])->toEqual(60);
});

it('AC-005: net is receivable minus payable minus committed and the cumulative skips the backlog', function () {
    mcReceivable([['due' => -2, 'amount' => '500.00'], ['due' => 0, 'amount' => '100.00'], ['due' => mcMonthDue(1), 'amount' => '300.00']]);
    mcPayable([['due' => -2, 'amount' => '70.00'], ['due' => 0, 'amount' => '30.00'], ['due' => mcMonthDue(2), 'amount' => '50.00']]);
    mcOrderLine(null, '20.00', order: ['order_date' => mcDay(0)]);
    mcOrderLine(null, '10.00', order: ['order_date' => mcDay(-9)]);

    $data = mcGet('cash-flow')->assertOk()->json('data');
    $months = $data['months'];

    expect($data['backlog'])->toEqual(['receivable' => 500, 'payable' => 70, 'committed' => 10, 'net' => 420])
        ->and($months[0])->toMatchArray(['month' => mcMonth(0), 'receivable' => 100, 'payable' => 30, 'committed' => 20, 'net' => 50, 'cumulative' => 50])
        ->and($months[1])->toMatchArray(['receivable' => 300, 'payable' => 0, 'committed' => 0, 'net' => 300, 'cumulative' => 350])
        ->and($months[2])->toMatchArray(['receivable' => 0, 'payable' => 50, 'net' => -50, 'cumulative' => 300])
        ->and($months[5]['cumulative'])->toEqual(300);
});

it('AC-005: the document type excludes the proforma from receivables and payables', function () {
    mcReceivable([['due' => 5, 'amount' => '100.00']]);
    mcReceivable([['due' => 5, 'amount' => '40.00']], ['type' => InvoiceType::Proforma]);
    mcPayable([['due' => 5, 'amount' => '10.00']]);
    mcPayable([['due' => 5, 'amount' => '3.00']], ['document_type' => PurchaseInvoiceType::Proforma]);

    $totals = function (string $type): array {
        $data = mcGet('cash-flow', ['document_type' => $type])->assertOk()->json('data');
        $sum = fn (string $side): float => array_sum(array_column($data['months'], $side)) + $data['beyond'][$side];

        return [$sum('receivable'), $sum('payable')];
    };

    expect($totals('all'))->toEqual([140, 13])
        ->and($totals('invoice'))->toEqual([100, 10])
        ->and($totals('proforma'))->toEqual([40, 3]);
});
