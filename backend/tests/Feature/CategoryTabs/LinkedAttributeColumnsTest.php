<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// A legacy field linked to its current one shares its column (spec 0429): AC-001..AC-007.

uses(RefreshDatabase::class);

/**
 * A current root with `new_date` and a legacy root linked to it with `old_date`, linked to `new_date`.
 *
 * @return array{0: ProductCategory, 1: ProductCategory}
 */
function linkedAttributeRoots(AttributeContext $context): array
{
    [$current, $currentField] = categoryTabAttribute($context, 'date', ['code' => 'new_date', 'name' => 'Data di assegnazione']);
    $legacy = categoryTabLegacyRoot($current);
    [, $legacyField] = categoryTabAttribute($context, 'date', ['code' => 'old_date', 'name' => 'Data di assegnazione'], $legacy);
    $legacyField->update(['current_attribute_id' => $currentField->id]);

    return [$current, $legacy];
}

/**
 * Two work orders on the current root and two on the legacy one, keyed by name.
 *
 * @return array<string, int>
 */
function linkedAttributeWorkOrders(ProductCategory $current, ProductCategory $legacy): array
{
    $values = ['new_early' => [$current, ['new_date' => '2025-01-10']], 'new_late' => [$current, ['new_date' => '2025-09-10']],
        'old_mid' => [$legacy, ['old_date' => '2025-05-10']], 'old_empty' => [$legacy, null]];
    $ids = [];

    foreach ($values as $name => [$category, $attributeValues]) {
        $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
        $workOrder->forceFill(['attribute_values' => $attributeValues])->save();
        $ids[$name] = $workOrder->id;
    }

    return $ids;
}

/**
 * @param  array<string, mixed>  $extra
 * @return array<int, array<string, mixed>>
 */
function linkedAttributeRows(ProductCategory $current, ProductCategory $legacy, array $extra = []): array
{
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => [$current->id, $legacy->id]]];

    return test()->postJson('/api/tables/work-orders/rows', [...$page, ...$extra])->assertOk()->json('items');
}

it('shows one column for the linked fields, the legacy one alone when only its category is selected (AC-001)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    categoryTabSaveFilters('work-orders', ['parent_category' => [$current->id, $legacy->id]])->assertOk();
    expect(filterDrivenAttrIds(categoryTabConfigColumns('work-orders')))->toBe(['attr.new_date']);

    categoryTabSaveFilters('work-orders', categoryTabFilters($legacy))->assertOk();
    expect(filterDrivenAttrIds(categoryTabConfigColumns('work-orders')))->toBe(['attr.old_date']);
});

it('reads the current value on new records and the legacy one on migrated records (AC-002)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    $ids = linkedAttributeWorkOrders($current, $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $rows = collect(linkedAttributeRows($current, $legacy))->keyBy('id');

    expect($rows[$ids['new_early']]['attr.new_date'])->toBe('2025-01-10')
        ->and($rows[$ids['old_mid']]['attr.new_date'])->toBe('2025-05-10')
        ->and($rows[$ids['old_empty']]['attr.new_date'])->toBeNull()
        ->and($rows[$ids['old_mid']])->not->toHaveKey('attr.old_date');
});

it('filters a date range and the blank rule across both fields (AC-003)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    $ids = linkedAttributeWorkOrders($current, $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $inRange = linkedAttributeRows($current, $legacy, ['filterModel' => ['attr.new_date' => [
        'filterType' => 'date', 'type' => 'inRange', 'dateFrom' => '2025-01-01', 'dateTo' => '2025-06-30',
    ]]]);
    $blank = linkedAttributeRows($current, $legacy, ['filterModel' => ['attr.new_date' => ['filterType' => 'set', 'values' => [null]]]]);

    expect(array_column($inRange, 'id'))->toEqualCanonicalizing([$ids['new_early'], $ids['old_mid']])
        ->and(array_column($blank, 'id'))->toBe([$ids['old_empty']]);
});

it('sorts new and migrated records together by value (AC-004)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    $ids = linkedAttributeWorkOrders($current, $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $sorted = linkedAttributeRows($current, $legacy, ['sortModel' => [['colId' => 'attr.new_date', 'sort' => 'desc']]]);

    expect(array_slice(array_column($sorted, 'id'), 0, 3))->toBe([$ids['new_late'], $ids['old_mid'], $ids['new_early']]);
});

it('lists the distinct values of both fields (AC-005)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    linkedAttributeWorkOrders($current, $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'attr.new_date'])->assertOk()->json('data.values');

    expect($values)->toContain('2025-01-10', '2025-09-10', '2025-05-10');
});

it('writes the field the record carries and answers under the merged column (AC-006)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::WorkOrder);
    $ids = linkedAttributeWorkOrders($current, $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $onLegacy = $this->patchJson("/api/tables/work-orders/rows/{$ids['old_mid']}", ['column' => 'attr.new_date', 'value' => '2025-07-01'])->assertOk();
    $onCurrent = $this->patchJson("/api/tables/work-orders/rows/{$ids['new_early']}", ['column' => 'attr.new_date', 'value' => '2025-02-02'])->assertOk();

    expect($onLegacy->json('data')['attr.new_date'])->toBe('2025-07-01')
        ->and(WorkOrder::findOrFail($ids['old_mid'])->attribute_values)->toBe(['old_date' => '2025-07-01'])
        ->and($onCurrent->json('data')['attr.new_date'])->toBe('2025-02-02')
        ->and(WorkOrder::findOrFail($ids['new_early'])->attribute_values)->toBe(['new_date' => '2025-02-02']);
});

it('merges the offer fields on the quotes and contracts grids (AC-007)', function () {
    [$current, $legacy] = linkedAttributeRoots(AttributeContext::Quote);
    $newQuote = categoryTabQuote([$current]);
    $newQuote->forceFill(['attribute_values' => ['new_date' => '2025-03-03']])->save();
    $oldContract = categoryTabContract([$legacy]);
    $oldContract->quote->forceFill(['attribute_values' => ['old_date' => '2025-04-04']])->save();
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => ['parent_category' => [$current->id, $legacy->id]]];
    $actor = categoryTabGridActor('quotes');
    $actor->givePermissionTo(categoryTabGridActor('contracts')->getAllPermissions());
    Sanctum::actingAs($actor);

    $quotes = collect($this->postJson('/api/tables/quotes/rows', $page)->assertOk()->json('items'))->keyBy('id');
    $contracts = $this->postJson('/api/tables/contracts/rows', [...$page, 'filterModel' => ['attr.new_date' => [
        'filterType' => 'date', 'type' => 'equals', 'dateFrom' => '2025-04-04',
    ]]])->assertOk()->json('items');
    categoryTabSaveFilters('contracts', ['parent_category' => [$current->id, $legacy->id]])->assertOk();

    expect($quotes[$newQuote->id]['attr.new_date'])->toBe('2025-03-03')
        ->and($quotes[$oldContract->quote_id]['attr.new_date'])->toBe('2025-04-04')
        ->and(array_column($contracts, 'id'))->toBe([$oldContract->id])
        ->and($contracts[0]['attr.new_date'])->toBe('2025-04-04')
        ->and(filterDrivenAttrIds(categoryTabConfigColumns('contracts')))->toBe(['attr.new_date']);
});

it('keeps two columns for fields that are not linked', function () {
    [$current] = categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => 'new_date', 'name' => 'Data']);
    $legacy = categoryTabLegacyRoot($current);
    categoryTabAttribute(AttributeContext::WorkOrder, 'date', ['code' => 'old_date', 'name' => 'Data'], $legacy);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    categoryTabSaveFilters('work-orders', ['parent_category' => [$current->id, $legacy->id]])->assertOk();

    expect(filterDrivenAttrIds(categoryTabConfigColumns('work-orders')))->toBe(['attr.new_date', 'attr.old_date'])
        ->and(Attribute::query()->whereNotNull('current_attribute_id')->count())->toBe(0);
});
