<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

// Task 427477: a `table` attribute (summary on the selected row) without a selected row shows "N righe";
// its filter falls back to the summary column of every row, the summary keeps priority when present.

uses(RefreshDatabase::class);

/**
 * @param  array<int, array{0: string, 1: bool}>  $rows  inspection date + active flag
 */
function inspectionsWorkOrder(ProductCategory $category, array $rows): WorkOrder
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
function inspectionsGrid(): array
{
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'table', ['code' => 'inspections', 'config' => inspectionTableConfig()]);
    $workOrders = [
        'unselected' => inspectionsWorkOrder($category, [['2024-04-08', false], ['2024-04-10', false]]),
        'selected' => inspectionsWorkOrder($category, [['2025-01-10', true], ['2024-04-01', false]]),
        'empty' => inspectionsWorkOrder($category, []),
    ];
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    return [$category, $workOrders];
}

/**
 * @param  array<string, mixed>  $filter
 * @param  array<string, WorkOrder>  $workOrders
 * @return array<int, string>
 */
function inspectionsFiltered(ProductCategory $category, array $workOrders, array $filter): array
{
    $ids = test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => categoryTabFilters($category),
        'filterModel' => ['attr.inspections' => $filter],
    ])->assertOk()->json('items.*.id');
    $names = array_flip(array_map(static fn (WorkOrder $workOrder): int => $workOrder->id, $workOrders));

    return collect($ids)->map(fn (int $id): string => $names[$id])->sort()->values()->all();
}

it('matches a row of an unselected table and the summary of a selected one (text filter)', function (): void {
    [$category, $workOrders] = inspectionsGrid();
    $contains = ['filterType' => 'text', 'type' => 'contains', 'filter' => '2024-04'];

    expect(inspectionsFiltered($category, $workOrders, $contains))->toBe(['unselected'])
        ->and(inspectionsFiltered($category, $workOrders, [...$contains, 'filter' => '2025-01']))->toBe(['selected'])
        ->and(inspectionsFiltered($category, $workOrders, [...$contains, 'type' => 'equals', 'filter' => '2024-04-10']))->toBe(['unselected'])
        ->and(inspectionsFiltered($category, $workOrders, [...$contains, 'type' => 'notContains']))->toBe(['empty', 'selected']);
});

it('reads the typed and the set sub-filters of the multi filter the grid sends', function (): void {
    [$category, $workOrders] = inspectionsGrid();
    $text = ['filterType' => 'text', 'type' => 'contains', 'filter' => '2024-04'];

    expect(inspectionsFiltered($category, $workOrders, ['filterType' => 'multi', 'filterModels' => [null, $text]]))->toBe(['unselected'])
        ->and(inspectionsFiltered($category, $workOrders, ['filterType' => 'multi', 'filterModels' => [
            ['filterType' => 'set', 'values' => ['2024-04-10', '2025-01-10']],
            null,
        ]]))->toBe(['selected', 'unselected']);
});

it('lists the summary or, without one, every row value as distinct filter values', function (): void {
    [$category] = inspectionsGrid();

    $values = $this->postJson('/api/tables/work-orders/values', [
        'columnId' => 'attr.inspections',
        'advancedFilters' => categoryTabFilters($category),
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['2024-04-08', '2024-04-10', '2025-01-10']);
});
