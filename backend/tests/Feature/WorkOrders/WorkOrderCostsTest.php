<?php

use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\VatRate;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Commessa costs (spec 0190): GET/PUT /api/work-orders/{workOrder}/costs.
 */
uses(RefreshDatabase::class);

/**
 * A commessa with two REVENUE lines (A, B), a cost-only product and, on the
 * offer, COST lines: one allocated to A, one to a line of ANOTHER commessa,
 * one generic.
 *
 * @return array{workOrder: WorkOrder, lineA: QuoteLine, lineB: QuoteLine, product: Product}
 */
function costsScenario(): array
{
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $lineA = QuoteLine::factory()->create(['quote_id' => $quote->id, 'quantity' => 1, 'unit_price' => 1000, 'net_amount' => 1000, 'total_amount' => 1000]);
    $lineB = QuoteLine::factory()->create(['quote_id' => $quote->id, 'quantity' => 1, 'unit_price' => 500, 'net_amount' => 500, 'total_amount' => 500]);
    $workOrder->quoteLines()->attach([$lineA->id, $lineB->id]);

    return ['workOrder' => $workOrder, 'lineA' => $lineA, 'lineB' => $lineB, 'product' => Product::factory()->costOnly()->create()];
}

/** @return array<string, mixed> */
function costRow(Product $product, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'vat_rate_id' => null,
        'quote_line_id' => null,
        'incurred_on' => '2026-10-01',
        'supplier_id' => null,
        'document_reference' => null,
        'additional_description' => null,
        ...$overrides,
    ];
}

it('AC-001: GET returns the overview shape for a user with viewCosts', function () {
    ['workOrder' => $workOrder] = costsScenario();
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts']));

    $this->getJson("/api/work-orders/{$workOrder->id}/costs")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => [
            'lines',
            'budget' => ['allocated_lines', 'unallocated_lines'],
            'comparison' => [
                'rows' => [['quote_line_id', 'product' => ['id', 'code', 'name'], 'revenue_net', 'budget_cost_net', 'actual_cost_net', 'delta_net']],
                'unattributed_actual_cost_net',
                'totals' => ['revenue_net', 'budget_cost_net', 'actual_cost_net', 'delta_net', 'budget_margin_net', 'actual_margin_net'],
            ],
        ]]);
});

it('AC-002: GET is 403 without viewCosts, or when the commessa is not visible', function () {
    ['workOrder' => $workOrder] = costsScenario();

    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));
    $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertForbidden();

    Permission::findOrCreate('work-orders.viewCosts');
    $outsider = User::factory()->create();
    $outsider->givePermissionTo('work-orders.viewCosts');
    Sanctum::actingAs($outsider);
    $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertForbidden();

    $this->getJson('/api/work-orders/999999/costs')->assertNotFound();
});

it('AC-003/AC-004: budget lists only COST lines allocated to this commessa, generic ones apart and out of totals', function () {
    ['workOrder' => $workOrder, 'lineA' => $lineA, 'product' => $product] = costsScenario();
    $otherWorkOrder = WorkOrder::factory()->create(['quote_id' => $workOrder->quote_id]);
    $foreignRevenue = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $otherWorkOrder->quoteLines()->attach($foreignRevenue->id);

    $mine = QuoteLine::factory()->cost()->create(['quote_id' => $workOrder->quote_id, 'product_id' => $product->id, 'offer_line_id' => $lineA->id, 'net_amount' => 300, 'total_amount' => 300]);
    QuoteLine::factory()->cost()->create(['quote_id' => $workOrder->quote_id, 'offer_line_id' => $foreignRevenue->id, 'net_amount' => 700, 'total_amount' => 700]);
    $generic = QuoteLine::factory()->cost()->create(['quote_id' => $workOrder->quote_id, 'offer_line_id' => null, 'net_amount' => 900, 'total_amount' => 900]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts']));

    $response = $this->getJson("/api/work-orders/{$workOrder->id}/costs")->assertOk();

    expect(collect($response->json('data.budget.allocated_lines'))->pluck('id')->all())->toBe([$mine->id])
        ->and(collect($response->json('data.budget.unallocated_lines'))->pluck('id')->all())->toBe([$generic->id]);
    $response->assertJsonPath('data.comparison.totals.budget_cost_net', '300.00');
});

it('AC-005: PUT creates rows with server-computed amounts and a frozen unit of measure', function () {
    ['workOrder' => $workOrder, 'product' => $product] = costsScenario();
    $unit = UnitOfMeasure::factory()->create();
    $product->update(['unit_of_measure_id' => $unit->id]);
    $vat = VatRate::factory()->create(['rate' => 22]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $response = $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costRow($product, ['quantity' => 3, 'unit_price' => 10.5, 'vat_rate_id' => $vat->id])],
    ])->assertOk()->assertJsonPath('success', true);

    $response->assertJsonPath('data.lines.0.net_amount', '31.50')
        ->assertJsonPath('data.lines.0.vat_amount', '6.93')
        ->assertJsonPath('data.lines.0.total_amount', '38.43')
        ->assertJsonPath('data.lines.0.unit_of_measure.id', $unit->id)
        ->assertJsonPath('data.lines.0.incurred_on', '2026-10-01');

    $product->update(['unit_of_measure_id' => UnitOfMeasure::factory()->create()->id]);
    $id = WorkOrderCost::firstOrFail()->id;
    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costRow($product, ['id' => $id, 'quantity' => 3, 'unit_price' => 10.5])],
    ])->assertOk()->assertJsonPath('data.lines.0.unit_of_measure.id', $unit->id);

    foreach (['net_amount', 'vat_amount', 'total_amount', 'unit_of_measure_id'] as $forbidden) {
        $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
            'lines' => [costRow($product, [$forbidden => 1])],
        ])->assertUnprocessable()->assertJsonValidationErrors(["lines.0.{$forbidden}"]);
    }
});

it('AC-006: PUT deletes absent costs, updates by id and uses the array order as sort_order', function () {
    ['workOrder' => $workOrder, 'product' => $product] = costsScenario();
    $keep = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'product_id' => $product->id, 'sort_order' => 0]);
    $drop = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'product_id' => $product->id, 'sort_order' => 1]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costRow($product, ['document_reference' => 'NEW']), costRow($product, ['id' => $keep->id, 'quantity' => 4, 'unit_price' => 25])],
    ])->assertOk();

    expect(WorkOrderCost::find($drop->id))->toBeNull()
        ->and($keep->fresh()->sort_order)->toBe(1)
        ->and($keep->fresh()->net_amount)->toBe('100.00')
        ->and($workOrder->costs()->first()->document_reference)->toBe('NEW')
        ->and($workOrder->costs()->count())->toBe(2);

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => []])->assertOk();
    expect($workOrder->costs()->count())->toBe(0);
});

it('AC-007: PUT is 403 without manageCosts and changes nothing', function () {
    ['workOrder' => $workOrder, 'product' => $product] = costsScenario();
    $existing = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'product_id' => $product->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => []])->assertForbidden();

    expect(WorkOrderCost::find($existing->id))->not->toBeNull();
});

it('AC-008: PUT rejects foreign quote_line, foreign cost id, non-COST product and non-supplier with 422', function () {
    ['workOrder' => $workOrder, 'product' => $product] = costsScenario();
    $foreignLine = QuoteLine::factory()->create(['quote_id' => $workOrder->quote_id]);
    $foreignCost = WorkOrderCost::factory()->create(['product_id' => $product->id]);
    $saleProduct = Product::factory()->saleOnly()->create();
    $notSupplier = Registry::factory()->create(['is_supplier' => false]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $cases = [
        'lines.0.quote_line_id' => costRow($product, ['quote_line_id' => $foreignLine->id]),
        'lines.0.id' => costRow($product, ['id' => $foreignCost->id]),
        'lines.0.product_id' => costRow($saleProduct),
        'lines.0.supplier_id' => costRow($product, ['supplier_id' => $notSupplier->id]),
        'lines.0.incurred_on' => costRow($product, ['incurred_on' => null]),
        'lines.0.document_reference' => costRow($product, ['document_reference' => str_repeat('x', 101)]),
        'lines.0.additional_description' => costRow($product, ['additional_description' => str_repeat('x', 5001)]),
    ];

    foreach ($cases as $field => $row) {
        $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [$row]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field]);
    }

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => array_fill(0, 201, costRow($product))])
        ->assertUnprocessable()->assertJsonValidationErrors(['lines']);

    expect(WorkOrderCost::where('work_order_id', $workOrder->id)->count())->toBe(0);
});

it('AC-008: PUT rejects the same cost id twice in one payload with 422', function () {
    ['workOrder' => $workOrder, 'product' => $product] = costsScenario();
    $existing = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id, 'product_id' => $product->id, 'quantity' => 1]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costRow($product, ['id' => $existing->id, 'quantity' => 3]), costRow($product, ['id' => $existing->id, 'quantity' => 4])],
    ])->assertUnprocessable()->assertJsonValidationErrors(['lines.0.id', 'lines.1.id']);

    expect($existing->fresh()->quantity)->toBe('1.00');
});

it('AC-008 (positive): a registered supplier and an own revenue line are accepted', function () {
    ['workOrder' => $workOrder, 'lineA' => $lineA, 'product' => $product] = costsScenario();
    $supplier = Registry::factory()->create(['is_supplier' => true]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costRow($product, ['quote_line_id' => $lineA->id, 'supplier_id' => $supplier->id])],
    ])->assertOk()
        ->assertJsonPath('data.lines.0.supplier.id', $supplier->id)
        ->assertJsonPath('data.lines.0.quote_line_id', $lineA->id);
});

it('AC-009: comparison rows, unattributed cost and totals are computed per spec', function () {
    ['workOrder' => $workOrder, 'lineA' => $lineA, 'lineB' => $lineB, 'product' => $product] = costsScenario();
    QuoteLine::factory()->cost()->create(['quote_id' => $workOrder->quote_id, 'offer_line_id' => $lineA->id, 'net_amount' => 300, 'total_amount' => 300]);
    QuoteLine::factory()->cost()->create(['quote_id' => $workOrder->quote_id, 'offer_line_id' => null, 'net_amount' => 900, 'total_amount' => 900]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $response = $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [
        costRow($product, ['quantity' => 1, 'unit_price' => 350, 'quote_line_id' => $lineA->id]),
        costRow($product, ['quantity' => 1, 'unit_price' => 120.5, 'quote_line_id' => null]),
    ]])->assertOk();

    $response->assertJsonPath('data.comparison.rows.0.quote_line_id', $lineA->id)
        ->assertJsonPath('data.comparison.rows.0.revenue_net', '1000.00')
        ->assertJsonPath('data.comparison.rows.0.budget_cost_net', '300.00')
        ->assertJsonPath('data.comparison.rows.0.actual_cost_net', '350.00')
        ->assertJsonPath('data.comparison.rows.0.delta_net', '50.00')
        ->assertJsonPath('data.comparison.rows.1.quote_line_id', $lineB->id)
        ->assertJsonPath('data.comparison.rows.1.budget_cost_net', '0.00')
        ->assertJsonPath('data.comparison.unattributed_actual_cost_net', '120.50')
        ->assertJsonPath('data.comparison.totals.revenue_net', '1500.00')
        ->assertJsonPath('data.comparison.totals.budget_cost_net', '300.00')
        ->assertJsonPath('data.comparison.totals.actual_cost_net', '470.50')
        ->assertJsonPath('data.comparison.totals.delta_net', '170.50')
        ->assertJsonPath('data.comparison.totals.budget_margin_net', '1200.00')
        ->assertJsonPath('data.comparison.totals.actual_margin_net', '1029.50');

    expect($this->getJson("/api/work-orders/{$workOrder->id}/costs")->json('data.comparison.totals.actual_cost_net'))->toBe('470.50');
});

it('AC-010: the work order detail exposes view_costs / manage_costs in permissions.actions', function () {
    ['workOrder' => $workOrder] = costsScenario();

    Sanctum::actingAs(workOrderCostsUserWith(['view', 'viewCosts']));
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.view_costs', true)
        ->assertJsonPath('permissions.actions.manage_costs', false);

    Sanctum::actingAs(workOrderCostsUserWith(['view', 'manageCosts']));
    $this->getJson("/api/work-orders/{$workOrder->id}")
        ->assertJsonPath('permissions.actions.view_costs', false)
        ->assertJsonPath('permissions.actions.manage_costs', true);
});
