<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// Bug 2026-11-24 ("Ente di riferimento" on /work-orders): a text/number
// attribute column is an agMultiColumnFilter, so the grid sends its filter
// wrapped as `multi` (or a combined `{operator, conditions}`); the per-type
// handler only reads the flat shape and the filter was silently dropped.

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $values  code => stored value, per row name
 * @return array{0: ProductCategory, 1: array<string, WorkOrder>}
 */
function scalarAttributeGrid(string $type, string $code, array $values): array
{
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, $type, ['code' => $code]);
    $workOrders = [];

    foreach ($values as $name => $value) {
        $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
        $workOrder->forceFill(['attribute_values' => $value === null ? null : [$code => $value]])->save();
        $workOrders[$name] = $workOrder;
    }

    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    return [$category, $workOrders];
}

/**
 * @param  array<string, WorkOrder>  $workOrders
 * @param  array<string, mixed>  $filter
 * @return array<int, string>
 */
function scalarAttributeFiltered(ProductCategory $category, array $workOrders, string $code, array $filter): array
{
    $ids = test()->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'advancedFilters' => categoryTabFilters($category),
        'filterModel' => ["attr.{$code}" => $filter],
    ])->assertOk()->json('items.*.id');
    $names = array_flip(array_map(static fn (WorkOrder $workOrder): int => $workOrder->id, $workOrders));

    return collect($ids)->map(fn (int $id): string => $names[$id])->sort()->values()->all();
}

it('applies the set and the text sub-filters of the multi filter on a text attribute', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('text', 'reference_body', ['amtivo' => 'AMTIVO', 'iqi' => 'IQI', 'blank' => null]);
    $multi = static fn (?array $text, ?array $set): array => ['filterType' => 'multi', 'filterModels' => [$text, $set]];

    expect(scalarAttributeFiltered($category, $workOrders, 'reference_body', $multi(null, ['filterType' => 'set', 'values' => ['IQI']])))->toBe(['iqi'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'reference_body', $multi(['filterType' => 'text', 'type' => 'contains', 'filter' => 'amt'], null)))->toBe(['amtivo'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'reference_body', $multi(null, ['filterType' => 'set', 'values' => [null]])))->toBe(['blank']);
});

it('applies a combined text condition on a text attribute', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('text', 'reference_body', ['amtivo' => 'AMTIVO', 'iqi' => 'IQI', 'other' => 'BUREAU']);
    $combined = ['filterType' => 'text', 'operator' => 'OR', 'conditions' => [
        ['filterType' => 'text', 'type' => 'equals', 'filter' => 'IQI'],
        ['filterType' => 'text', 'type' => 'startsWith', 'filter' => 'AM'],
    ]];

    expect(scalarAttributeFiltered($category, $workOrders, 'reference_body', $combined))->toBe(['amtivo', 'iqi']);
});

it('applies the number sub-filter of the multi filter on a decimal attribute', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('decimal', 'work_hours', ['low' => 2.5, 'high' => 12]);
    $number = ['filterType' => 'number', 'type' => 'greaterThan', 'filter' => 5];

    expect(scalarAttributeFiltered($category, $workOrders, 'work_hours', ['filterType' => 'multi', 'filterModels' => [$number, null]]))->toBe(['high']);
});

it('never matches a stored JSON null with a number condition', function (): void {
    // MySQL casts the JSON null read through the path ('null') to 0; SQLite does not, so this pins the outcome only.
    [$category, $workOrders] = scalarAttributeGrid('decimal', 'quotation', ['zero' => 0, 'five' => 5, 'empty' => 0]);
    $workOrders['empty']->forceFill(['attribute_values' => ['quotation' => null]])->save();
    $number = static fn (string $type, float $value): array => ['filterType' => 'multi', 'filterModels' => [['filterType' => 'number', 'type' => $type, 'filter' => $value], null]];

    expect(scalarAttributeFiltered($category, $workOrders, 'quotation', $number('equals', 0)))->toBe(['zero'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'quotation', $number('notEqual', 5)))->toBe(['zero'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'quotation', $number('lessThan', 5)))->toBe(['zero'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'quotation', ['filterType' => 'multi', 'filterModels' => [null, ['filterType' => 'set', 'values' => [null]]]]))->toBe(['empty']);
});

it('filters a boolean attribute by the string values the set filter round-trips, blank included', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('boolean', 'contract_sent', ['yes' => true, 'no' => false, 'blank' => null]);
    $set = static fn (array $values): array => ['filterType' => 'set', 'values' => $values];

    expect(scalarAttributeFiltered($category, $workOrders, 'contract_sent', $set(['false'])))->toBe(['no'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'contract_sent', $set(['true'])))->toBe(['yes'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'contract_sent', $set(['true', 'false'])))->toBe(['no', 'yes'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'contract_sent', $set([null])))->toBe(['blank'])
        ->and(scalarAttributeFiltered($category, $workOrders, 'contract_sent', $set([null, '1'])))->toBe(['blank', 'yes']);
});

it('finds a searched value beyond the first 500 distinct values of an attribute', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('text', 'scope', ['target' => 'zz pest management']);
    WorkOrder::factory()->count(501)->sequence(fn ($sequence): array => [
        'quote_id' => null,
        'attribute_values' => ['scope' => sprintf('aa scope %03d', $sequence->index)],
    ])->create();

    $values = test()->postJson('/api/tables/work-orders/values', [
        'columnId' => 'attr.scope', 'filterModel' => new stdClass, 'limit' => 50, 'search' => 'PEST',
        'advancedFilters' => categoryTabFilters($category),
    ])->assertOk()->json('data.values');

    expect($values)->toBe(['zz pest management']);
});

it('keeps the flat text filter working', function (): void {
    [$category, $workOrders] = scalarAttributeGrid('text', 'reference_body', ['amtivo' => 'AMTIVO', 'iqi' => 'IQI']);

    expect(scalarAttributeFiltered($category, $workOrders, 'reference_body', ['filterType' => 'text', 'type' => 'equals', 'filter' => 'IQI']))->toBe(['iqi']);
});
