<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0431, AC-008 (D-12): a commessa born without an offer in the income statement.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

/** A commessa born without an offer, in $company / $operational, on the given business functions. */
function mcStandaloneWorkOrder(Company $company, ?OperationalSite $operational, BusinessFunction ...$functions): WorkOrder
{
    $workOrder = standaloneProjectWorkOrder([
        'company_id' => $company->id,
        'company_site_id' => CompanySite::factory()->create(['company_id' => $company->id, 'name' => 'Sede Interna'])->id,
        'operational_site_id' => $operational?->id,
        'start_date' => '2026-03-10',
    ]);
    $workOrder->productLines()->delete();
    foreach ($functions as $function) {
        $workOrder->productLines()->create([
            'business_function_id' => $function->id,
            'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $function->id])->id,
        ]);
    }

    return $workOrder;
}

it('segments it by its own company and sites', function () {
    $company = Company::factory()->create(['denomination' => 'Interna']);
    $operational = OperationalSite::factory()->create(['alias' => 'Bari']);
    mcManualCost(mcStandaloneWorkOrder($company, $operational, BusinessFunction::factory()->create()), '30.00');

    $label = fn (string $segmentBy): array => array_column(mcGet('income-statement', ['segment_by' => $segmentBy])->assertOk()->json('data.segments'), 'label');

    expect($label('company'))->toBe(['Interna'])
        ->and($label('company_site'))->toBe(['Sede Interna'])
        ->and($label('operational_site'))->toBe(['Bari'])
        ->and($label('commercial'))->toBe([null]);
});

it('is kept by the company, site and business-function filters on its own values', function () {
    $company = Company::factory()->create();
    $operational = OperationalSite::factory()->create();
    $function = BusinessFunction::factory()->create();
    mcManualCost(mcStandaloneWorkOrder($company, $operational, $function), '30.00');
    mcManualCost(mcWorkOrder(), '5.00');

    $count = fn (array $params): int => mcGet('income-statement', $params)->assertOk()->json('data.totals.work_orders_count');

    expect($count(['company_id' => [$company->id]]))->toBe(1)
        ->and($count(['operational_site_id' => [$operational->id]]))->toBe(1)
        ->and($count(['business_function_id' => [$function->id]]))->toBe(1);
});

it('splits its costs in equal parts among its own business functions, with no revenue', function () {
    [$sales, $support] = [BusinessFunction::factory()->create(['name' => 'Sales']), BusinessFunction::factory()->create(['name' => 'Support'])];
    mcManualCost(mcStandaloneWorkOrder(Company::factory()->create(), null, $sales, $support), '100.00');

    $data = mcGet('income-statement')->assertOk()->json('data');
    $functions = collect($data['by_business_function'])->keyBy('label');

    expect($functions['Sales']['cost_manual'])->toEqual(50)
        ->and($functions['Support']['cost_manual'])->toEqual(50)
        ->and($data['totals']['revenue_contract'])->toEqual(0)
        ->and($functions->has(null))->toBeFalse();
});
