<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Spec 0272, AC-003 / AC-004 — the internal labor cost in the income statement.
uses(RefreshDatabase::class);

beforeEach(fn () => Sanctum::actingAs(mcActor()));

it('AC-003: the hours are valued per user, unpriced ones are counted apart and the cost and margins include the labor', function () {
    $workOrder = mcWorkOrder();
    mcRevenueLine($workOrder, '1000.00');
    mcManualCost($workOrder, '100.00');

    mcTimeEntry($workOrder, 90, '30.00');
    mcTimeEntry($workOrder, 120, '20.00');
    mcTimeEntry($workOrder, 60, null);
    mcTimeEntry($workOrder, 30, null, user: User::factory()->create());
    mcTimeEntry(null, 480, '50.00');

    $totals = mcGet('income-statement')->assertOk()->json('data.totals');

    expect($totals['cost_labor'])->toEqual(85)
        ->and($totals['labor_minutes'])->toBe(300)
        ->and($totals['unpriced_minutes'])->toBe(90)
        ->and($totals['cost_actual'])->toEqual(185)
        ->and($totals['margin_actual'])->toEqual(815)
        ->and($totals['cost_delta'])->toEqual(185)
        ->and($totals['margin_budget'])->toEqual(1000)
        ->and($totals['margin_delta'])->toEqual(-185);
});

it('AC-003: the cost is rounded once to the cent from the exact sum of minutes and hourly cost', function () {
    $workOrder = mcWorkOrder();
    mcTimeEntry($workOrder, 1, '10.00');
    mcTimeEntry($workOrder, 1, '10.00');
    mcTimeEntry($workOrder, 1, '10.00');

    expect(mcGet('income-statement')->assertOk()->json('data.totals.cost_labor'))->toEqual(0.5);
});

it('AC-004: hours and labor cost split among the functions as the other figures, the last share absorbing the rounding', function () {
    [$sales, $support] = [BusinessFunction::factory()->create(['name' => 'Sales']), BusinessFunction::factory()->create(['name' => 'Support'])];
    $workOrder = mcWorkOrder();
    mcRevenueLine($workOrder, '300.00', $sales);
    mcRevenueLine($workOrder, '100.00', $support);
    mcTimeEntry($workOrder, 100, '30.00');
    mcTimeEntry($workOrder, 11, null);

    $data = mcGet('income-statement')->assertOk()->json('data');
    $functions = collect($data['by_business_function'])->keyBy('key');

    expect($functions[(string) $sales->id]['cost_labor'])->toEqual(37.5)
        ->and($functions[(string) $support->id]['cost_labor'])->toEqual(12.5)
        ->and($functions[(string) $sales->id]['labor_minutes'])->toBe(83)
        ->and($functions[(string) $support->id]['labor_minutes'])->toBe(28)
        ->and($functions[(string) $sales->id]['unpriced_minutes'])->toBe(8)
        ->and($functions[(string) $support->id]['unpriced_minutes'])->toBe(3)
        ->and($functions[(string) $sales->id]['cost_actual'])->toEqual(37.5)
        ->and($data['totals']['cost_labor'])->toEqual(50)
        ->and($data['totals']['labor_minutes'])->toBe(111)
        ->and($data['totals']['unpriced_minutes'])->toBe(11);
});

it('AC-004: segments sum the labor of their commesse and nest the split by function', function () {
    $function = BusinessFunction::factory()->create(['name' => 'Sales']);
    $first = mcWorkOrder(['type' => 'project']);
    $second = mcWorkOrder(['type' => 'processing']);
    mcRevenueLine($first, '100.00', $function);
    mcTimeEntry($first, 60, '40.00');
    mcTimeEntry($second, 30, '40.00');

    $segments = collect(mcGet('income-statement', ['segment_by' => 'work_order_type'])->assertOk()->json('data.segments'))->keyBy('key');

    expect($segments['project']['cost_labor'])->toEqual(40)
        ->and($segments['project']['labor_minutes'])->toBe(60)
        ->and($segments['project']['by_business_function'][0]['cost_labor'])->toEqual(40)
        ->and($segments['processing']['cost_labor'])->toEqual(20)
        ->and($segments['processing']['by_business_function'][0]['key'])->toBe('unattributed')
        ->and($segments['processing']['by_business_function'][0]['labor_minutes'])->toBe(30);
});

it('AC-008: the number of queries does not depend on the commesse and time entries', function () {
    $seed = function (int $count): void {
        for ($i = 0; $i < $count; $i++) {
            $workOrder = mcWorkOrder();
            mcRevenueLine($workOrder, '100.00', BusinessFunction::factory()->create());
            mcTimeEntry($workOrder, 60, '20.00');
            mcTimeEntry($workOrder, 30, null);
        }
    };

    $seed(5);
    $few = mcQueryCount('income-statement', ['segment_by' => 'work_order_type']);
    $seed(20);

    expect(mcQueryCount('income-statement', ['segment_by' => 'work_order_type']))->toBe($few);
});
