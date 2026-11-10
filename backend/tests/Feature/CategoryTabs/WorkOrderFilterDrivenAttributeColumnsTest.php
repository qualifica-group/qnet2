<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// attr.<code> columns of the work-orders grid, driven by the category filter (spec 0374): AC-009, AC-012.

uses(RefreshDatabase::class);

it('lists the WorkOrder-context attributes of the filtered category and not the Quote-only ones (AC-009)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field', 'name' => 'Campo commessa']);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'quote_only'], $category);
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    categoryTabSaveFilters('work-orders', categoryTabFilters($category))->assertOk();

    $columns = categoryTabConfigColumns('work-orders');

    expect(array_column($columns, 'id'))->toContain('attr.wo_field')->not->toContain('attr.quote_only')
        ->and(categoryTabColumn($columns, 'attr.wo_field')['editable'])->toBeTrue();
});

it('reads work_orders.attribute_values, filters, sorts and lists distinct values (AC-009)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    $betaQuote = categoryTabQuote([$category]);
    $betaQuote->forceFill(['attribute_values' => ['wo_field' => 'from-quote']])->save();
    $beta = categoryTabMultiWorkOrder($betaQuote);
    $alpha = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $beta->forceFill(['attribute_values' => ['wo_field' => 'beta']])->save();
    $alpha->forceFill(['attribute_values' => ['wo_field' => 'alpha']])->save();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $page = ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => categoryTabFilters($category)];

    $rows = $this->postJson('/api/tables/work-orders/rows', $page)->assertOk()->json('items');
    expect(collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.wo_field']])->all())
        ->toBe([$beta->id => 'beta', $alpha->id => 'alpha']);

    $sorted = $this->postJson('/api/tables/work-orders/rows', [...$page, 'sortModel' => [['colId' => 'attr.wo_field', 'sort' => 'asc']]])->json('items');
    expect(array_column($sorted, 'id'))->toBe([$alpha->id, $beta->id]);

    $filtered = $this->postJson('/api/tables/work-orders/rows', [...$page, 'filterModel' => ['attr.wo_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]])->json('items');
    expect(array_column($filtered, 'id'))->toBe([$alpha->id]);

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'attr.wo_field'])->assertOk()->json('data.values');
    expect($values)->toContain('alpha', 'beta')->not->toContain('from-quote');
});

it('writes on work_orders.attribute_values and leaves the offer untouched (AC-009)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'integer', ['code' => 'wo_field']);
    $quote = categoryTabQuote([$category]);
    $workOrder = categoryTabMultiWorkOrder($quote);
    $workOrder->forceFill(['attribute_values' => ['other' => 'kept']])->save();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $response = $this->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", ['column' => 'attr.wo_field', 'value' => 7])->assertOk();

    expect($response->json('data')['attr.wo_field'])->toBe(7)
        ->and($workOrder->fresh()->attribute_values)->toBe(['other' => 'kept', 'wo_field' => 7])
        ->and($quote->fresh()->attribute_values)->toBeNull();
});

it('refuses the write without permission and for an inapplicable code (AC-009)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'integer', ['code' => 'wo_field']);
    categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'foreign']);
    $workOrder = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $url = "/api/tables/work-orders/rows/{$workOrder->id}";
    $noUpdate = workOrderUserWith(['viewAny']);
    $readOnlyRole = categoryTabAttributeRoleActor('work-orders', editable: false);
    $writer = workOrderUserWith(['viewAny', 'update']);

    Sanctum::actingAs($noUpdate);
    $this->patchJson($url, ['column' => 'attr.wo_field', 'value' => 1])->assertForbidden();

    Sanctum::actingAs($readOnlyRole);
    $this->patchJson($url, ['column' => 'attr.wo_field', 'value' => 1])->assertForbidden();

    Sanctum::actingAs($writer);
    $this->patchJson($url, ['column' => 'attr.foreign', 'value' => 'x'])->assertUnprocessable();
    $this->patchJson($url, ['column' => 'attr.wo_field', 'value' => 'nan'])->assertUnprocessable();

    expect($workOrder->fresh()->attribute_values)->toBeNull();
});

it('keeps a hidden attribute column hidden on reload (AC-013)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    categoryTabAssertAttributeStaysHidden('work-orders', $category, 'wo_field');
});

it('shows rows and distinct values of the visible work orders only (AC-012)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    $actor = categoryTabGridActor('work-orders', viewAll: false);
    $mine = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $mine->forceFill(['attribute_values' => ['wo_field' => 'visible-value']])->save();
    $mine->supervisors()->sync([$actor->id]);
    categoryTabMultiWorkOrder(categoryTabQuote([$category]))->forceFill(['attribute_values' => ['wo_field' => 'hidden-value']])->save();
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, 'advancedFilters' => categoryTabFilters($category)])->assertOk()->json('items');
    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'attr.wo_field'])->assertOk()->json('data.values');

    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and($values)->toContain('visible-value')->not->toContain('hidden-value');
});
