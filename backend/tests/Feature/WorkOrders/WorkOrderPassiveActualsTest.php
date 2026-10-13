<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Passive cycle in the commessa actuals (spec 0252): GET /api/work-orders/{workOrder}/costs.
 */
uses(RefreshDatabase::class);

/** An order line of the commessa on an order with the given status. */
function passiveOrderLine(WorkOrder $workOrder, PurchaseOrderStatus $status, string $amount, ?CostType $type = null, ?WorkOrder $lineWorkOrder = null): PurchaseOrderLine
{
    return PurchaseOrderLine::factory()->create([
        'purchase_order_id' => PurchaseOrder::factory()->withStatus($status),
        'work_order_id' => ($lineWorkOrder ?? $workOrder)->id,
        'cost_type_id' => ($type ?? CostType::factory()->create())->id,
        'taxable_amount' => $amount,
    ]);
}

function passiveDocumentLine(?WorkOrder $workOrder, string $amount, ?CostType $type = null, ?PurchaseOrderLine $orderLine = null): PurchaseInvoiceLine
{
    return PurchaseInvoiceLine::factory()->create([
        'purchase_order_line_id' => $orderLine?->id,
        'work_order_id' => $workOrder?->id,
        'cost_type_id' => ($type ?? CostType::factory()->create())->id,
        'net_amount' => $amount,
    ]);
}

function manualCost(WorkOrder $workOrder, string $amount, ?CostType $type = null): WorkOrderCost
{
    return WorkOrderCost::factory()->create([
        'work_order_id' => $workOrder->id,
        'cost_type_id' => $type?->id,
        'net_amount' => $amount,
        'total_amount' => $amount,
    ]);
}

/** @return array<string, mixed> */
function passiveTotals(WorkOrder $workOrder): array
{
    return test()->getJson("/api/work-orders/{$workOrder->id}/costs")->assertOk()->json('data.comparison');
}

beforeEach(fn () => Sanctum::actingAs(workOrderCostsUserWith(['viewCosts'])));

it('AC-001: only invoiceable orders are committed, added to the manual costs', function () {
    $workOrder = WorkOrder::factory()->create();
    manualCost($workOrder, '100.00');
    passiveOrderLine($workOrder, PurchaseOrderStatus::Approved, '250.00');
    passiveOrderLine($workOrder, PurchaseOrderStatus::Draft, '999.00');

    $totals = passiveTotals($workOrder)['totals'];

    expect($totals['manual_cost_net'])->toBe('100.00')
        ->and($totals['committed_order_cost_net'])->toBe('250.00')
        ->and($totals['invoiced_cost_net'])->toBe('0.00')
        ->and($totals['total_actual_cost_net'])->toBe('350.00')
        ->and($totals['total_delta_net'])->toBe('350.00')
        ->and($totals['total_actual_margin_net'])->toBe('-350.00');
});

it('AC-002: a billed order line stops counting as committed, the document amount replaces it', function () {
    $workOrder = WorkOrder::factory()->create();
    $orderLine = passiveOrderLine($workOrder, PurchaseOrderStatus::SentToSupplier, '250.00');
    passiveDocumentLine($workOrder, '270.00', null, $orderLine);

    $totals = passiveTotals($workOrder)['totals'];

    expect($totals['committed_order_cost_net'])->toBe('0.00')
        ->and($totals['invoiced_cost_net'])->toBe('270.00')
        ->and($totals['total_actual_cost_net'])->toBe('270.00');
});

it('AC-003: free document lines count, other commesse and cancelled orders do not', function () {
    $workOrder = WorkOrder::factory()->create();
    $other = WorkOrder::factory()->create();
    passiveDocumentLine($workOrder, '80.00');
    passiveDocumentLine($other, '500.00');
    passiveDocumentLine(null, '700.00');
    passiveOrderLine($workOrder, PurchaseOrderStatus::Cancelled, '300.00');
    passiveOrderLine($workOrder, PurchaseOrderStatus::Fulfilled, '40.00', null, $other);

    $totals = passiveTotals($workOrder)['totals'];

    expect($totals['invoiced_cost_net'])->toBe('80.00')
        ->and($totals['committed_order_cost_net'])->toBe('0.00')
        ->and($totals['total_actual_cost_net'])->toBe('80.00');
});

it('AC-004: by_cost_type groups the three sources, untyped last, summing to the total', function () {
    $workOrder = WorkOrder::factory()->create();
    $second = CostType::factory()->create(['sort_order' => 2, 'name' => 'B', 'color' => 'red']);
    $first = CostType::factory()->create(['sort_order' => 1, 'name' => 'A', 'color' => 'blue']);
    manualCost($workOrder, '10.00', $second);
    manualCost($workOrder, '5.00');
    passiveOrderLine($workOrder, PurchaseOrderStatus::Approved, '20.00', $first);
    passiveDocumentLine($workOrder, '30.00', $first);
    passiveDocumentLine($workOrder, '7.50', $second);

    $comparison = passiveTotals($workOrder);
    $groups = $comparison['by_cost_type'];

    expect(array_column(array_column($groups, 'cost_type'), 'id'))->toBe([$first->id, $second->id])
        ->and($groups[0])->toMatchArray(['committed_order_cost_net' => '20.00', 'invoiced_cost_net' => '30.00', 'manual_cost_net' => '0.00', 'total_cost_net' => '50.00'])
        ->and($groups[0]['cost_type'])->toMatchArray(['name' => 'A', 'color' => 'blue', 'nature' => $first->nature->value])
        ->and($groups[1])->toMatchArray(['manual_cost_net' => '10.00', 'invoiced_cost_net' => '7.50', 'total_cost_net' => '17.50'])
        ->and($groups[2])->toMatchArray(['cost_type' => null, 'manual_cost_net' => '5.00', 'total_cost_net' => '5.00'])
        ->and(array_sum(array_map('floatval', array_column($groups, 'total_cost_net'))))->toBe((float) $comparison['totals']['total_actual_cost_net']);
});

it('AC-005: the existing fields keep their meaning (actual_cost_net is the manual costs only)', function () {
    $workOrder = WorkOrder::factory()->create();
    manualCost($workOrder, '100.00');
    passiveDocumentLine($workOrder, '60.00');

    $comparison = passiveTotals($workOrder);

    expect($comparison['totals']['actual_cost_net'])->toBe('100.00')
        ->and($comparison['totals']['delta_net'])->toBe('100.00')
        ->and($comparison['unattributed_actual_cost_net'])->toBe('100.00')
        ->and($comparison)->toHaveKeys(['rows', 'totals', 'unattributed_actual_cost_net', 'by_cost_type']);
});

it('AC-006: the number of queries does not grow with the rows', function () {
    $queries = function (WorkOrder $workOrder): int {
        DB::enableQueryLog();
        $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        DB::flushQueryLog();

        return $count;
    };
    $fill = function (WorkOrder $workOrder, int $rows): void {
        foreach (range(1, $rows) as $ignored) {
            $type = CostType::factory()->create();
            manualCost($workOrder, '1.00', $type);
            passiveOrderLine($workOrder, PurchaseOrderStatus::Approved, '2.00', $type);
            passiveDocumentLine($workOrder, '3.00', $type, passiveOrderLine($workOrder, PurchaseOrderStatus::Fulfilled, '4.00', $type));
        }
    };
    $small = WorkOrder::factory()->create();
    $fill($small, 1);
    $large = WorkOrder::factory()->create();
    $fill($large, 8);
    $queries($small);

    expect($queries($large))->toBe($queries($small));
});

it('denies the totals without work-orders.viewCosts', function () {
    $workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderCostsUserWith([]));

    $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertForbidden();
});
