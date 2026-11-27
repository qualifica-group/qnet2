<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// The custom filter rules reach the `attr.*` columns (their declaration carries the filterType):
// a date attribute answers blank / not blank and the date operators, the grid's value list too.

uses(RefreshDatabase::class);

/**
 * @return array{0: ProductCategory, 1: array<string, WorkOrder>}
 */
function dateAttributeGrid(): array
{
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => 'audit_date']);
    $workOrder = function (?string $date) use ($category): WorkOrder {
        $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
        $workOrder->forceFill(['attribute_values' => $date === null ? [] : ['audit_date' => $date]])->save();

        return $workOrder;
    };
    $workOrders = ['early' => $workOrder('2025-01-10'), 'late' => $workOrder('2025-06-01'), 'none' => $workOrder(null)];
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    return [$category, $workOrders];
}

/**
 * @param  array<string, WorkOrder>  $workOrders
 * @param  array<string, mixed>  $request
 * @return array<int, string>
 */
function dateAttributeRows(ProductCategory $category, array $workOrders, array $request): array
{
    $ids = test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => workOrderCategoryFilters($category),
        ...$request,
    ])->assertOk()->json('items.*.id');
    $names = array_flip(array_map(static fn (WorkOrder $workOrder): int => $workOrder->id, $workOrders));

    return collect($ids)->map(fn (int $id): string => $names[$id])->sort()->values()->all();
}

it('applies blank, not blank and the date operators of a custom filter rule', function (): void {
    [$category, $workOrders] = dateAttributeGrid();
    $ruled = fn (array $rule): array => dateAttributeRows($category, $workOrders, ['customFilterRules' => ['and' => [
        ['field' => 'attr.audit_date', ...$rule],
    ]]]);

    expect($ruled(['operator' => 'not_blank']))->toBe(['early', 'late'])
        ->and($ruled(['operator' => 'blank']))->toBe(['none'])
        ->and($ruled(['operator' => 'lt', 'value' => '2025-03-01']))->toBe(['early']);
});

it('reads the set sub-filter of the grid, blank entry included', function (): void {
    [$category, $workOrders] = dateAttributeGrid();
    $filtered = fn (array $filter): array => dateAttributeRows($category, $workOrders, ['filterModel' => ['attr.audit_date' => $filter]]);

    expect($filtered(['filterType' => 'multi', 'filterModels' => [['filterType' => 'set', 'values' => ['2025-06-01', null]], null]]))->toBe(['late', 'none'])
        ->and($filtered(['filterType' => 'date', 'type' => 'greaterThan', 'dateFrom' => '2025-02-01 00:00:00']))->toBe(['late']);
});
