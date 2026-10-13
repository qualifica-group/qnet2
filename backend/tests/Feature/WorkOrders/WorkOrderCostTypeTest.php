<?php

use App\Models\CostType;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Cost type on a commessa's actual costs (spec 0251, AC-005).
 */
uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function costTypeCostRow(Product $product, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => 50,
        'incurred_on' => '2026-10-01',
        ...$overrides,
    ];
}

it('AC-005: PUT saves the cost type of a row and GET returns it', function () {
    $workOrder = WorkOrder::factory()->create();
    $product = Product::factory()->costOnly()->create();
    $costType = CostType::factory()->create(['name' => 'Rent', 'color' => 'blue']);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts', 'viewCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costTypeCostRow($product, ['cost_type_id' => $costType->id])],
    ])->assertOk()
        ->assertJsonPath('data.lines.0.cost_type_id', $costType->id)
        ->assertJsonPath('data.lines.0.cost_type', ['id' => $costType->id, 'name' => 'Rent', 'color' => 'blue']);

    $this->getJson("/api/work-orders/{$workOrder->id}/costs")
        ->assertOk()
        ->assertJsonPath('data.lines.0.cost_type.id', $costType->id);
});

it('AC-005: a row without cost_type_id stays null', function () {
    $workOrder = WorkOrder::factory()->create();
    $product = Product::factory()->costOnly()->create();
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [costTypeCostRow($product)]])
        ->assertOk()
        ->assertJsonPath('data.lines.0.cost_type_id', null)
        ->assertJsonPath('data.lines.0.cost_type', null);

    expect(WorkOrderCost::query()->firstOrFail()->cost_type_id)->toBeNull();
});

it('AC-005: an unknown cost type is rejected', function () {
    $workOrder = WorkOrder::factory()->create();
    $product = Product::factory()->costOnly()->create();
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [costTypeCostRow($product, ['cost_type_id' => 999999])],
    ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.cost_type_id');
});
