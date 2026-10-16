<?php

use App\Enums\InvoiceType;
use App\Enums\PurchaseOrderStatus;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\OperationalSite;
use App\Models\ProductCategory;
use App\Models\Referent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

// Spec 0268, AC-008 / AC-009 / AC-014 — the statement by function, the segments and the constant query count.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

const MC_SUMMED_FIGURES = ['revenue_contract', 'cost_manual', 'margin_actual', 'margin_budget', 'cost_budget'];

it('AC-008: functions split a commessa by revenue weight, unattributed last, counts per function', function () {
    [$sales, $support] = [BusinessFunction::factory()->create(['name' => 'Sales']), BusinessFunction::factory()->create(['name' => 'Support'])];
    $first = mcWorkOrder();
    mcRevenueLine($first, '300.00', $sales);
    mcRevenueLine($first, '100.00', $support);
    mcManualCost($first, '100.00');
    $second = mcWorkOrder();
    mcRevenueLine($second, '100.00', $sales);
    mcRevenueLine($second, '100.00', null);
    mcManualCost($second, '200.00');
    mcManualCost(mcWorkOrder(), '50.00');

    $data = mcGet('income-statement')->assertOk()->json('data');
    $functions = collect($data['by_business_function'])->keyBy('key');

    expect(array_column($data['by_business_function'], 'key'))->toBe([(string) $sales->id, (string) $support->id, 'unattributed'])
        ->and($data['by_business_function'][2]['label'])->toBeNull()
        ->and($data['by_business_function'][0]['label'])->toBe('Sales')
        ->and($functions[(string) $sales->id]['cost_manual'])->toEqual(175)
        ->and($functions[(string) $sales->id]['revenue_contract'])->toEqual(400)
        ->and($functions[(string) $support->id]['cost_manual'])->toEqual(25)
        ->and($functions['unattributed']['cost_manual'])->toEqual(150)
        ->and($functions['unattributed']['revenue_contract'])->toEqual(100)
        ->and($functions[(string) $sales->id]['work_orders_count'])->toBe(2)
        ->and($functions[(string) $support->id]['work_orders_count'])->toBe(1)
        ->and($functions['unattributed']['work_orders_count'])->toBe(2)
        ->and($data['totals']['work_orders_count'])->toBe(3);

    foreach (MC_SUMMED_FIGURES as $figure) {
        expect(round(array_sum(array_column($data['by_business_function'], $figure)), 2))->toEqual($data['totals'][$figure]);
    }
});

it('AC-009: every segment dimension has one entry per value with exact counts and a nested statement', function () {
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $companyA = Company::factory()->create(['denomination' => 'Alfa']);
    $companyB = Company::factory()->create(['denomination' => 'Beta']);
    $siteA = CompanySite::factory()->create(['company_id' => $companyA->id, 'name' => 'Sede A']);
    $siteB = CompanySite::factory()->create(['company_id' => $companyB->id, 'name' => 'Sede B']);
    $operational = OperationalSite::factory()->create(['alias' => 'Torino']);
    $agent = Referent::factory()->create(['name' => 'Rossi']);

    $quotes = [
        ['company_id' => $companyA->id, 'company_site_id' => $siteA->id, 'operational_site_id' => $operational->id, 'commercial_id' => $agent->id],
        ['company_id' => $companyB->id, 'company_site_id' => $siteB->id, 'operational_site_id' => $operational->id, 'commercial_id' => $agent->id],
        [],
    ];
    foreach ($quotes as $index => $quote) {
        $workOrder = mcWorkOrder(['type' => $index === 1 ? 'processing' : 'project'], $quote);
        mcManualCost($workOrder, '10.00');
        mcRevenueLine($workOrder, '100.00', $index === 0 ? $function : null);
    }

    $expectations = [
        'company' => [['Alfa', 1], ['Beta', 1], [null, 1]],
        'company_site' => [['Sede A', 1], ['Sede B', 1], [null, 1]],
        'operational_site' => [['Torino', 2], [null, 1]],
        'commercial' => [['Rossi', 2], [null, 1]],
        'work_order_type' => [[null, 1], [null, 2]],
    ];
    foreach ($expectations as $segmentBy => $expected) {
        $data = mcGet('income-statement', ['segment_by' => $segmentBy])->assertOk()->json('data');

        expect(array_map(fn (array $segment): array => [$segment['label'], $segment['work_orders_count']], $data['segments']))->toBe($expected);
        foreach (MC_SUMMED_FIGURES as $figure) {
            expect(round(array_sum(array_column($data['segments'], $figure)), 2))->toEqual($data['totals'][$figure]);
            foreach ($data['segments'] as $segment) {
                expect(round(array_sum(array_column($segment['by_business_function'], $figure)), 2))->toEqual($segment[$figure]);
            }
        }
    }

    $byType = mcGet('income-statement', ['segment_by' => 'work_order_type'])->json('data.segments');
    expect(array_column($byType, 'key'))->toBe(['processing', 'project']);
    $byCompany = mcGet('income-statement', ['segment_by' => 'company'])->json('data.segments');
    expect(array_column($byCompany, 'key')[2])->toBe('unattributed')
        ->and(array_column($byCompany[0]['by_business_function'], 'label'))->toBe(['Sales']);
    expect(mcGet('income-statement', ['segment_by' => 'none'])->json('data.segments'))->toBe([]);

    mcGet('income-statement', ['segment_by' => 'business_function'])->assertUnprocessable();
    mcGet('income-statement', ['segment_by' => 'customer'])->assertUnprocessable();
});

it('AC-014: the number of queries does not grow with the number of commesse, with or without segments', function () {
    $count = function (string $segmentBy): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        mcGet('income-statement', ['segment_by' => $segmentBy])->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $addWorkOrders = function (int $n): void {
        foreach (range(1, $n) as $_) {
            $workOrder = mcWorkOrder([], ['company_id' => Company::factory()->create()->id]);
            $line = mcRevenueLine($workOrder, '100.00', BusinessFunction::factory()->create());
            mcCostLine('30.00', $line);
            mcManualCost($workOrder, '5.00');
            mcOrderLine($workOrder, '20.00');
            mcPayableLine($workOrder, '10.00');
            mcInvoice($workOrder, InvoiceType::Invoice, '100.00', '122.00', '61.00');
            mcOrderLine($workOrder, '99.00', PurchaseOrderStatus::Draft);
        }
    };

    $addWorkOrders(5);
    $count('none'); // warm-up: the actor's permissions load on the first request only
    $few = ['none' => $count('none'), 'company' => $count('company')];
    $addWorkOrders(20);

    expect($count('none'))->toBe($few['none'])->and($count('company'))->toBe($few['company']);
});

it('AC-008: a category without its own function weighs on the function inherited from its ancestors', function () {
    $sales = BusinessFunction::factory()->create(['name' => 'Sales']);
    $root = ProductCategory::factory()->create(['business_function_id' => $sales->id]);
    $child = ProductCategory::factory()->create(['parent_id' => $root->id]);
    $grandchild = ProductCategory::factory()->create(['parent_id' => $child->id]);
    $bareRoot = ProductCategory::factory()->create();
    $bareChild = ProductCategory::factory()->create(['parent_id' => $bareRoot->id]);

    $workOrder = mcWorkOrder();
    foreach ([[$grandchild, '300.00'], [$bareChild, '100.00']] as [$category, $net]) {
        mcRevenueLine($workOrder, $net)->product->update(['category_id' => $category->id]);
    }
    mcManualCost($workOrder, '100.00');

    $functions = collect(mcGet('income-statement')->assertOk()->json('data.by_business_function'))->keyBy('key');

    expect($functions->pluck('key')->all())->toBe([(string) $sales->id, 'unattributed'])
        ->and($functions[(string) $sales->id]['cost_manual'])->toEqual(75)
        ->and($functions['unattributed']['cost_manual'])->toEqual(25);
});
