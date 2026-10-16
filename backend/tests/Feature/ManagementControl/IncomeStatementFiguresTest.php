<?php

use App\Enums\CommissionRecipientRole;
use App\Enums\InvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SupplierCommissionDirection;
use App\Models\BusinessFunction;
use App\Models\CommissionAccrual;
use App\Models\Company;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0268, AC-003 / AC-004 / AC-010 — the figures of a commessa.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/** The commessa of AC-003: see the expected numbers in the assertions. */
function mcFullWorkOrder(): WorkOrder
{
    $workOrder = mcWorkOrder(['code' => 'COM-0001'], ['company_id' => Company::factory()->create(['denomination' => 'Alfa Srl'])->id]);
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $paid = mcRevenueLine($workOrder, '1000.00', $function, SupplierCommissionDirection::Paid);
    $received = mcRevenueLine($workOrder, '500.00', $function, SupplierCommissionDirection::Received);
    mcCommission($paid, CommissionRecipientRole::Commercial, '50.00');
    mcCommission($paid, CommissionRecipientRole::Supplier, '20.00');
    mcCommission($received, CommissionRecipientRole::Supplier, '40.00');
    mcCostLine('300.00', $paid);
    mcCostLine('100.00', $received);
    mcCostLine('77.00', null, $workOrder->quote_id);

    mcManualCost($workOrder, '25.00', $paid);
    mcManualCost($workOrder, '15.00');
    $orderLine = mcOrderLine($workOrder, '100.00');
    mcOrderLine($workOrder, '999.00', PurchaseOrderStatus::Draft);
    mcPayableLine($workOrder, '40.00', $orderLine, '0.400');
    mcPayableLine($workOrder, '10.00');

    mcInvoice($workOrder, InvoiceType::Invoice, '800.00', '976.00', '488.00');
    mcInvoice($workOrder, InvoiceType::Proforma, '200.00', '244.00', '244.00');

    CommissionAccrual::factory()->create(['quote_line_id' => $paid->id, 'type' => 'ACCRUAL', 'amount' => '70.00']);
    CommissionAccrual::factory()->create(['quote_line_id' => $paid->id, 'type' => 'REVERSAL', 'amount' => '-10.00']);

    return $workOrder;
}

it('AC-003: totals and the only function row report every figure of the commessa', function () {
    mcFullWorkOrder();

    $data = mcGet('income-statement')->assertOk()->json('data');

    expect($data['by_business_function'])->toHaveCount(1)
        ->and($data['by_business_function'][0]['label'])->toBe('Sales')
        ->and($data)->not->toHaveKeys(['rows', 'groups']);
    foreach ([$data['totals'], $data['by_business_function'][0]] as $row) {
        expect($row['revenue_contract'])->toEqual(1040)
            ->and($row['revenue_invoiced'])->toEqual(800)
            ->and($row['revenue_collected'])->toEqual(400)
            ->and($row['cost_budget'])->toEqual(400)
            ->and($row['cost_manual'])->toEqual(40)
            ->and($row['cost_committed'])->toEqual(60)
            ->and($row['cost_invoiced'])->toEqual(50)
            ->and($row['cost_actual'])->toEqual(150)
            ->and($row['commission_budget'])->toEqual(70)
            ->and($row['commission_actual'])->toEqual(60)
            ->and($row['margin_budget'])->toEqual(570)
            ->and($row['margin_actual'])->toEqual(830)
            ->and($row['cost_delta'])->toEqual(-250)
            ->and($row['margin_delta'])->toEqual(260)
            ->and($row['work_orders_count'])->toBe(1);
    }
});

it('AC-004: the revenue basis changes the actual margin only, an unknown one is refused', function () {
    mcFullWorkOrder();

    foreach (['contract' => 830, 'invoiced' => 590, 'collected' => 190] as $basis => $expected) {
        $data = mcGet('income-statement', ['revenue_basis' => $basis])->assertOk()->json('data');

        expect($data['applied']['revenue_basis'])->toBe($basis);
        foreach ([$data['totals'], $data['by_business_function'][0]] as $row) {
            expect($row['margin_actual'])->toEqual($expected)->and($row['margin_budget'])->toEqual(570);
        }
    }

    mcGet('income-statement', ['revenue_basis' => 'cash'])->assertUnprocessable();
});

it('AC-010: totals add up the commesse, no per-commessa lists, applied echoes the parameters', function () {
    mcManualCost(mcWorkOrder(['code' => 'COM-0003']), '30.00');
    mcManualCost(mcWorkOrder(['code' => 'COM-0001']), '10.00');
    mcManualCost(mcWorkOrder(['code' => 'COM-0002']), '20.00');

    $data = mcGet('income-statement')->assertOk()->json('data');

    expect($data)->not->toHaveKeys(['rows', 'groups'])
        ->and($data['totals']['work_orders_count'])->toBe(3)
        ->and($data['totals']['cost_manual'])->toEqual(60)
        ->and($data['totals']['margin_actual'])->toEqual(-60)
        ->and($data['segments'])->toBe([])
        ->and($data['applied'])->toBe([
            'date_from' => null, 'date_to' => null,
            'company_id' => [], 'company_site_id' => [], 'operational_site_id' => [],
            'business_function_id' => [], 'work_order_type' => [], 'commercial_id' => [],
            'revenue_basis' => 'contract', 'segment_by' => 'none',
        ]);
});
