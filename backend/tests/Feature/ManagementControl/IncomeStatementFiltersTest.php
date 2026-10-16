<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0268, AC-005 / AC-006 / AC-007 — which commesse an income statement selects.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/**
 * The manual cost total of the selected commesse: each test gives every commessa
 * a distinct cost, so the sum tells which ones were selected.
 *
 * @param  array<string, mixed>  $params
 */
function mcSelectedCost(array $params): float
{
    return (float) mcGet('income-statement', $params)->assertOk()->json('data.totals.cost_manual');
}

it('AC-005: only commesse starting inside the interval, bounds included, contribute', function () {
    foreach (['2026-01-31' => '1.00', '2026-02-01' => '10.00', '2026-02-28' => '100.00', '2026-03-01' => '1000.00'] as $date => $cost) {
        mcManualCost(mcWorkOrder(['start_date' => $date]), $cost);
    }

    $data = mcGet('income-statement', ['date_from' => '2026-02-01', 'date_to' => '2026-02-28', 'segment_by' => 'work_order_type'])->assertOk()->json('data');

    expect($data['totals']['work_orders_count'])->toBe(2)
        ->and($data['totals']['cost_manual'])->toEqual(110)
        ->and($data['by_business_function'][0]['cost_manual'])->toEqual(110)
        ->and($data['segments'][0]['cost_manual'])->toEqual(110)
        ->and(mcSelectedCost(['date_to' => '2026-01-31']))->toEqual(1)
        ->and(mcSelectedCost(['date_from' => '2026-03-01']))->toEqual(1000);

    mcGet('income-statement', ['date_from' => '2026-02-10', 'date_to' => '2026-02-01'])->assertUnprocessable();
    mcGet('income-statement', ['date_from' => '10/02/2026'])->assertUnprocessable();
});

it('AC-006: the reference offer decides the company, sites, commercial and type filters', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $siteA = CompanySite::factory()->create(['company_id' => $companyA->id]);
    $siteB = CompanySite::factory()->create(['company_id' => $companyB->id]);
    $operationalA = OperationalSite::factory()->create();
    $operationalB = OperationalSite::factory()->create();
    $commercialA = Referent::factory()->create();
    $commercialB = Referent::factory()->create();

    mcManualCost(mcWorkOrder(['type' => 'project'], ['company_id' => $companyA->id, 'company_site_id' => $siteA->id, 'operational_site_id' => $operationalA->id, 'commercial_id' => $commercialA->id]), '1.00');
    mcManualCost(mcWorkOrder(['type' => 'processing'], ['company_id' => $companyB->id, 'company_site_id' => $siteB->id, 'operational_site_id' => $operationalB->id, 'commercial_id' => $commercialB->id]), '10.00');

    $count = fn (array $params): int => mcGet('income-statement', $params)->assertOk()->json('data.totals.work_orders_count');

    expect(mcSelectedCost(['company_id' => [$companyA->id]]))->toEqual(1)
        ->and(mcSelectedCost(['company_id' => [$companyA->id, $companyB->id]]))->toEqual(11)
        ->and(mcSelectedCost(['company_site_id' => [$siteB->id]]))->toEqual(10)
        ->and(mcSelectedCost(['operational_site_id' => [$operationalA->id]]))->toEqual(1)
        ->and(mcSelectedCost(['commercial_id' => [$commercialB->id]]))->toEqual(10)
        ->and(mcSelectedCost(['work_order_type' => ['processing']]))->toEqual(10)
        ->and($count(['company_id' => [$companyA->id], 'work_order_type' => ['processing']]))->toBe(0);

    foreach (['company_id', 'company_site_id', 'operational_site_id', 'commercial_id', 'business_function_id'] as $key) {
        mcGet('income-statement', [$key => [999999]])->assertUnprocessable();
    }
    mcGet('income-statement', ['work_order_type' => ['unknown']])->assertUnprocessable();
});

it('AC-006: a multi-offer commessa takes the company of the source offer with the lowest id', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $lowest = Quote::factory()->create(['company_id' => $companyA->id]);
    $highest = Quote::factory()->create(['company_id' => $companyB->id]);
    $workOrder = WorkOrder::factory()->multiQuote()->create(['start_date' => '2026-03-10', 'type' => 'project']);
    mcRevenueLine($workOrder, '100.00', null, null, $highest);
    mcRevenueLine($workOrder, '100.00', null, null, $lowest);

    $count = fn (int $companyId): int => mcGet('income-statement', ['company_id' => [$companyId]])->assertOk()->json('data.totals.work_orders_count');

    expect($count($companyA->id))->toBe(1)->and($count($companyB->id))->toBe(0);
});

it('AC-007: a function filter keeps the whole commessa, both functions show their share', function () {
    [$sales, $support] = [BusinessFunction::factory()->create(['name' => 'Sales']), BusinessFunction::factory()->create(['name' => 'Support'])];
    $workOrder = mcWorkOrder();
    mcRevenueLine($workOrder, '300.00', $sales);
    mcRevenueLine($workOrder, '100.00', $support);
    mcManualCost($workOrder, '100.00');
    mcRevenueLine(mcWorkOrder(), '50.00', $support);

    $data = mcGet('income-statement', ['business_function_id' => [$sales->id]])->assertOk()->json('data');
    $functions = collect($data['by_business_function'])->keyBy('key');

    expect($data['totals']['work_orders_count'])->toBe(1)
        ->and($data['totals']['cost_manual'])->toEqual(100)
        ->and($data['totals']['revenue_contract'])->toEqual(400)
        ->and(array_column($data['by_business_function'], 'key'))->toBe([(string) $sales->id, (string) $support->id])
        ->and($functions[(string) $sales->id]['cost_manual'])->toEqual(75)
        ->and($functions[(string) $sales->id]['revenue_contract'])->toEqual(300)
        ->and($functions[(string) $support->id]['cost_manual'])->toEqual(25)
        ->and($functions[(string) $support->id]['revenue_contract'])->toEqual(100);
});

it('AC-007: the function filter also matches a line whose category inherits that function', function () {
    $sales = BusinessFunction::factory()->create();
    $root = ProductCategory::factory()->create(['business_function_id' => $sales->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    mcRevenueLine(mcWorkOrder(), '100.00')->product->update(['category_id' => $child->id]);
    mcRevenueLine(mcWorkOrder(), '100.00');

    $count = mcGet('income-statement', ['business_function_id' => [$sales->id]])->assertOk()->json('data.totals.work_orders_count');

    expect($count)->toBe(1);
});
