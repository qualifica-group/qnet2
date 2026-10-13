<?php

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Spec 0208 (AC-013): the commessa costs refuse a NEW product of an inactive
 * category, while a cost already on the commessa can still be re-saved.
 */
uses(RefreshDatabase::class);

/** @return array<string, mixed> */
function inactiveCategoryCostRow(Product $product, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'incurred_on' => '2026-10-01',
        ...$overrides,
    ];
}

it('AC-013: PUT refuses a NEW cost product of an inactive category with one message', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    $inactive = ProductCategory::factory()->childOf(ProductCategory::factory()->create(['is_active' => false]))->create();
    $product = Product::factory()->costOnly()->create(['category_id' => $inactive->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $response = $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [inactiveCategoryCostRow($product)],
    ])->assertUnprocessable()->assertJsonValidationErrors('lines.0.product_id');

    expect($response->json('errors')['lines.0.product_id'])->toBe([__('This product belongs to an inactive category.')])
        ->and(WorkOrderCost::count())->toBe(0);
});

it('AC-013: PUT keeps accepting a cost product persisted before its category was deactivated', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    $category = ProductCategory::factory()->create();
    $product = Product::factory()->costOnly()->create(['category_id' => $category->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));

    $this->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [inactiveCategoryCostRow($product)]])->assertOk();
    $category->update(['is_active' => false]);

    $id = WorkOrderCost::firstOrFail()->id;
    $this->putJson("/api/work-orders/{$workOrder->id}/costs", [
        'lines' => [inactiveCategoryCostRow($product, ['id' => $id, 'quantity' => 4])],
    ])->assertOk();
});
