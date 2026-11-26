<?php

use App\Enums\PurchaseInvoiceType;
use App\Services\WorkOrders\WorkOrderPassiveCostAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0431, AC-007 — payslips are cash, not cost (D-5).
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-007: structure costs and the monthly trend count the invoice only, the cash side counts the payslip', function () {
    $payslip = mcPayable([['due' => mcMonthDue(1), 'amount' => '1000.00']], [
        'document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null, 'document_date' => '2026-03-31',
    ]);
    mcPayableLine(null, '1000.00', invoice: $payslip);
    mcTrendCost('2026-03-10', '300.00');

    expect(mcGet('structure-costs')->assertOk()->json('data.totals.invoiced_net'))->toEqual(300);

    $trend = mcGet('monthly-trend', ['date_from' => '2026-03-01', 'date_to' => '2026-03-31'])->assertOk()->json('data');
    expect($trend['totals']['cost'])->toEqual([300]);

    expect(mcGet('exposure', ['document_type' => 'all'])->assertOk()->json('data.payable.open_total'))->toEqual(1000);

    $flow = mcGet('cash-flow')->assertOk()->json('data');
    expect(round(array_sum(array_column($flow['months'], 'net')) + $flow['backlog']['net'] + $flow['beyond']['net'], 2))->toEqual(-1000);
});

it('AC-007: a payslip line on a commessa is not a cost of the commessa', function () {
    $workOrder = mcWorkOrder();
    $payslip = mcPayable([['due' => 10, 'amount' => '700.00']], ['document_type' => PurchaseInvoiceType::Payslip, 'document_number' => null]);
    mcPayableLine($workOrder, '700.00', invoice: $payslip);
    mcPayableLine($workOrder, '50.00');

    $rows = app(WorkOrderPassiveCostAggregator::class)->byCostType($workOrder);

    expect($rows->sum('invoiced_cents'))->toEqual(5000);
});
