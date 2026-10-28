<?php

declare(strict_types=1);

use App\Enums\ProductUsage;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Registry;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/**
 * Spec 0320 D-9 / AC-011: the supplier of a commessa cost row against the
 * product's suppliers. REQUIREMENT CHANGED vs spec 0190 D-8 (supplier free).
 */
uses(RefreshDatabase::class);

function supplierCostsPut(object $test, WorkOrder $workOrder, array $row): TestResponse
{
    Permission::findOrCreate('work-orders.manageCosts');

    return $test->putJson("/api/work-orders/{$workOrder->id}/costs", ['lines' => [$row]]);
}

function supplierCostsRow(Product $product, ?int $supplierId, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 10,
        'vat_rate_id' => null,
        'quote_line_id' => null,
        'incurred_on' => '2026-10-01',
        'supplier_id' => $supplierId,
        'document_reference' => null,
        'additional_description' => null,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->workOrder = WorkOrder::factory()->create();
    Sanctum::actingAs(workOrderCostsUserWith(['manageCosts']));
});

it('AC-011: a product with suppliers and a null supplier is 422 required', function () {
    $product = productWithSupplier(['usages' => [ProductUsage::Cost]]);

    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, null))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.supplier_id' => __('work_order_costs.supplier_required')]);
});

it('AC-011: a supplier that is not one of the product suppliers is 422 not_of_product', function () {
    $product = productWithSupplier(['usages' => [ProductUsage::Cost]]);
    $other = Registry::factory()->supplier()->create();

    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, $other->id))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.supplier_id' => __('work_order_costs.supplier_not_of_product')]);
});

it('AC-011: one of the product suppliers is accepted', function () {
    $product = productWithSupplier(['usages' => [ProductUsage::Cost]]);

    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, defaultSupplierId($product)))->assertOk();
});

it('AC-011: a product without suppliers keeps the supplier free', function () {
    $product = Product::factory()->costOnly()->create();
    $any = Registry::factory()->supplier()->create();

    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, $any->id))->assertOk();
    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, null))->assertOk();
});

it('AC-011: an unchanged historic row is not revalidated, a changed supplier is', function () {
    $product = productWithSupplier(['usages' => [ProductUsage::Cost]]);
    $historic = Registry::factory()->supplier()->create();
    $cost = WorkOrderCost::factory()->create([
        'work_order_id' => $this->workOrder->id,
        'product_id' => $product->id,
        'supplier_id' => $historic->id,
    ]);

    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, $historic->id, ['id' => $cost->id]))->assertOk();

    $another = Registry::factory()->supplier()->create();
    supplierCostsPut($this, $this->workOrder, supplierCostsRow($product, $another->id, ['id' => $cost->id]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.supplier_id']);

    expect(ProductSupplier::where('product_id', $product->id)->count())->toBe(1);
});
