<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// A `table` attribute with `summary.grid_label` on a date summary column: the grid column is
// that date alone ("Data verifica ispettiva" = the date of the active inspection row), blank
// without an active row, filtered and sorted as a date on the summary only.

uses(RefreshDatabase::class);

const SUMMARY_DATE_LABEL = 'Data verifica ispettiva';

/**
 * @param  array<int, array{0: string, 1: bool}>  $rows  inspection date + active flag
 */
function summaryDateWorkOrder(ProductCategory $category, array $rows): WorkOrder
{
    $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $selected = collect($rows)->first(fn (array $row): bool => $row[1]);
    $workOrder->forceFill(['attribute_values' => ['inspections' => [
        'rows' => array_map(fn (array $row): array => ['id' => (string) Str::uuid(), 'inspection_date' => $row[0], 'active' => $row[1]], $rows),
        'summary' => $selected[0] ?? null,
    ]]])->save();

    return $workOrder;
}

/**
 * @return array{0: ProductCategory, 1: array<string, WorkOrder>}
 */
function summaryDateGrid(): array
{
    $config = inspectionTableConfig(['summary' => ['column' => 'inspection_date', 'strategy' => 'selected', 'grid_label' => SUMMARY_DATE_LABEL]]);
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'table', ['code' => 'inspections', 'config' => $config]);
    $workOrders = [
        'inactive' => summaryDateWorkOrder($category, [['2024-04-08', false], ['2024-04-10', false]]),
        'active' => summaryDateWorkOrder($category, [['2025-01-10', true], ['2024-04-01', false]]),
        'later' => summaryDateWorkOrder($category, [['2025-03-02', true]]),
    ];
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    return [$category, $workOrders];
}

/**
 * @param  array<string, mixed>  $request
 * @param  array<string, WorkOrder>  $workOrders
 * @return array<int, string>
 */
function summaryDateRows(ProductCategory $category, array $workOrders, array $request): array
{
    $ids = test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => workOrderCategoryFilters($category),
        ...$request,
    ])->assertOk()->json('items.*.id');
    $names = array_flip(array_map(static fn (WorkOrder $workOrder): int => $workOrder->id, $workOrders));

    return collect($ids)->map(fn (int $id): string => $names[$id])->values()->all();
}

it('declares a date column labelled with the grid label', function (): void {
    [$category] = summaryDateGrid();

    categoryTabSaveFilters('work-orders', workOrderCategoryFilters($category))->assertOk();
    $column = collect(categoryTabConfigColumns('work-orders'))->firstWhere('id', 'attr.inspections');

    expect($column['label'])->toBe(SUMMARY_DATE_LABEL)
        ->and($column['type'])->toBe('datetime')
        ->and($column['filterType'])->toBe('date')
        ->and($column['editable'])->toBeFalse()
        ->and($column)->not->toHaveKey('table');
});

it('shows only the date of the active row, blank without one', function (): void {
    [$category, $workOrders] = summaryDateGrid();

    $items = collect($this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => workOrderCategoryFilters($category),
    ])->assertOk()->json('items'))->keyBy('id');

    expect($items[$workOrders['active']->id]['attr.inspections'])->toBe('2025-01-10')
        ->and($items[$workOrders['later']->id]['attr.inspections'])->toBe('2025-03-02')
        ->and($items[$workOrders['inactive']->id]['attr.inspections'])->toBeNull();
});

it('filters the summary date with the date and the set sub-filters, never the rows', function (): void {
    [$category, $workOrders] = summaryDateGrid();
    $range = ['filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2024-01-01 00:00:00', 'dateTo' => '2025-01-31 00:00:00'];
    $filtered = fn (array $filter): array => collect(summaryDateRows($category, $workOrders, ['filterModel' => ['attr.inspections' => $filter]]))->sort()->values()->all();

    expect($filtered($range))->toBe(['active'])
        ->and($filtered(['filterType' => 'multi', 'filterModels' => [null, $range]]))->toBe(['active'])
        ->and($filtered(['filterType' => 'multi', 'filterModels' => [['filterType' => 'set', 'values' => ['2025-03-02', null]], null]]))->toBe(['inactive', 'later'])
        ->and($filtered(['filterType' => 'set', 'values' => ['2024-04-10']]))->toBe([]);
});

it('sorts on the summary date', function (): void {
    [$category, $workOrders] = summaryDateGrid();

    $sorted = summaryDateRows($category, $workOrders, ['sortModel' => [['colId' => 'attr.inspections', 'sort' => 'desc']]]);

    expect(array_slice($sorted, 0, 2))->toBe(['later', 'active']);
});

it('lists the summary dates and the blank entry as distinct filter values', function (): void {
    [$category] = summaryDateGrid();

    $values = $this->postJson('/api/tables/work-orders/values', [
        'columnId' => 'attr.inspections',
        'advancedFilters' => workOrderCategoryFilters($category),
    ])->assertOk()->json('data.values');

    expect($values)->toBe([null, '2025-01-10', '2025-03-02']);
});

it('applies the custom filter rules (blank, not blank, after) to the summary date', function (): void {
    [$category, $workOrders] = summaryDateGrid();
    $ruled = fn (array $rule): array => collect(summaryDateRows($category, $workOrders, ['customFilterRules' => ['and' => [
        ['field' => 'attr.inspections', ...$rule],
    ]]]))->sort()->values()->all();

    expect($ruled(['operator' => 'not_blank']))->toBe(['active', 'later'])
        ->and($ruled(['operator' => 'blank']))->toBe(['inactive'])
        ->and($ruled(['operator' => 'gt', 'value' => '2025-02-01']))->toBe(['later']);
});
