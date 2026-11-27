<?php

use App\Enums\ProductUsage;
use App\Models\CostType;
use App\Models\Product;
use App\Models\ProductSupplier;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\Registry;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Sync of the actual costs from the offer (spec 0436):
 * POST /api/work-orders/{workOrder}/costs/sync-from-quote.
 */
uses(RefreshDatabase::class);

/**
 * A commessa with one REVENUE line and the offer's COST lines: one allocated
 * to it (supplier, cost type, description), one allocated to a line of
 * ANOTHER commessa, one generic.
 *
 * @return array{workOrder: WorkOrder, revenue: QuoteLine, allocated: QuoteLine, generic: QuoteLine, costType: CostType}
 */
function quoteCostSyncScenario(): array
{
    $quote = Quote::factory()->create();
    $workOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $revenue = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $workOrder->quoteLines()->attach($revenue->id);

    $otherWorkOrder = WorkOrder::factory()->create(['quote_id' => $quote->id]);
    $foreignRevenue = QuoteLine::factory()->create(['quote_id' => $quote->id]);
    $otherWorkOrder->quoteLines()->attach($foreignRevenue->id);

    $costType = CostType::factory()->create();
    $allocated = QuoteLine::factory()->cost()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->costOnly()->create(['cost_type_id' => $costType->id])->id,
        'offer_line_id' => $revenue->id,
        'quantity' => 2,
        'unit_price' => 30,
        'supplier_id' => Registry::factory()->supplier()->create()->id,
        'additional_description' => 'Docenza modulo 1',
        'sort_order' => 1,
    ]);
    QuoteLine::factory()->cost()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->costOnly()->create()->id,
        'offer_line_id' => $foreignRevenue->id,
        'sort_order' => 2,
    ]);
    $generic = QuoteLine::factory()->cost()->create([
        'quote_id' => $quote->id,
        'product_id' => Product::factory()->costOnly()->create()->id,
        'offer_line_id' => null,
        'quantity' => 1,
        'unit_price' => 100,
        'sort_order' => 3,
    ]);

    return ['workOrder' => $workOrder, 'revenue' => $revenue, 'allocated' => $allocated, 'generic' => $generic, 'costType' => $costType];
}

function syncCostsFromQuoteUrl(WorkOrder $workOrder): string
{
    return "/api/work-orders/{$workOrder->id}/costs/sync-from-quote";
}

it('AC-001/AC-003: copies the budget lines of this commessa, in the offer order, dated today', function () {
    ['workOrder' => $workOrder, 'revenue' => $revenue, 'allocated' => $allocated, 'generic' => $generic, 'costType' => $costType] = quoteCostSyncScenario();
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $response = $this->postJson(syncCostsFromQuoteUrl($workOrder))->assertOk()->assertJsonPath('success', true);

    $response->assertJsonCount(2, 'data.lines')
        ->assertJsonPath('data.lines.0.product_id', $allocated->product_id)
        ->assertJsonPath('data.lines.0.quote_line_id', $revenue->id)
        ->assertJsonPath('data.lines.0.cost_type_id', $costType->id)
        ->assertJsonPath('data.lines.0.supplier_id', $allocated->supplier_id)
        ->assertJsonPath('data.lines.0.net_amount', '60.00')
        ->assertJsonPath('data.lines.0.additional_description', 'Docenza modulo 1')
        ->assertJsonPath('data.lines.0.incurred_on', now()->toDateString())
        ->assertJsonPath('data.lines.0.document_reference', null)
        ->assertJsonPath('data.lines.1.product_id', $generic->product_id)
        ->assertJsonPath('data.lines.1.quote_line_id', null)
        ->assertJsonPath('data.lines.1.net_amount', '100.00')
        ->assertJsonPath('data.comparison.totals.actual_cost_net', '160.00');
});

it('AC-002: replaces every actual cost already entered', function () {
    ['workOrder' => $workOrder] = quoteCostSyncScenario();
    $existing = WorkOrderCost::factory()->count(3)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $this->postJson(syncCostsFromQuoteUrl($workOrder))->assertOk()->assertJsonCount(2, 'data.lines');

    expect(WorkOrderCost::query()->whereKey($existing->modelKeys())->exists())->toBeFalse()
        ->and($workOrder->costs()->count())->toBe(2);
});

it('AC-004: is 403 without manageCosts, costs untouched', function () {
    ['workOrder' => $workOrder] = quoteCostSyncScenario();
    $existing = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts']));

    $this->postJson(syncCostsFromQuoteUrl($workOrder))->assertForbidden();

    expect($workOrder->costs()->pluck('id')->all())->toBe([$existing->id]);
});

it('AC-005: is 422 when the offer has no cost lines for the commessa, costs untouched', function () {
    $workOrder = WorkOrder::factory()->create(['quote_id' => Quote::factory()->create()->id]);
    $existing = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $this->postJson(syncCostsFromQuoteUrl($workOrder))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);

    expect($workOrder->costs()->pluck('id')->all())->toBe([$existing->id]);
});

it('AC-006: is 422 naming the products no longer usable as a cost, costs untouched', function () {
    ['workOrder' => $workOrder, 'generic' => $generic] = quoteCostSyncScenario();
    $generic->product->update(['name' => 'Aula esterna', 'usages' => [ProductUsage::Sale]]);
    $existing = WorkOrderCost::factory()->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $response = $this->postJson(syncCostsFromQuoteUrl($workOrder))->assertUnprocessable();

    expect($response->json('errors.lines.0'))->toContain('Aula esterna')
        ->and($workOrder->costs()->pluck('id')->all())->toBe([$existing->id]);
});

it('AC-007: a line without supplier takes the product default supplier', function () {
    ['workOrder' => $workOrder, 'generic' => $generic] = quoteCostSyncScenario();
    $default = ProductSupplier::factory()->default()->create(['product_id' => $generic->product_id]);
    ProductSupplier::factory()->create(['product_id' => $generic->product_id]);
    Sanctum::actingAs(workOrderCostsUserWith(['viewCosts', 'manageCosts']));

    $this->postJson(syncCostsFromQuoteUrl($workOrder))
        ->assertOk()
        ->assertJsonPath('data.lines.1.supplier_id', $default->supplier_id);
});
