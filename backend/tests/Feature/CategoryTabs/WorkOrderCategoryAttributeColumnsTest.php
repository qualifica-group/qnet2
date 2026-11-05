<?php

declare(strict_types=1);

use App\Enums\AttributeContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// attr.<code> columns on the work-orders grid category tab (spec 0350, D-2): AC-004, 005, 008.

uses(RefreshDatabase::class);

it('lists the WorkOrder-context attributes of the tab and not the Quote-only ones (AC-004)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field', 'name' => 'Campo commessa']);
    categoryTabAttribute(AttributeContext::Quote, 'text', ['code' => 'quote_only'], $category);
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $columns = $this->getJson("/api/tables/work-orders/columns?product_category_id={$category->id}")->assertOk()->json('data.columns');
    $ids = array_column($columns, 'id');

    expect($ids)->toContain('attr.wo_field')->not->toContain('attr.quote_only')
        ->and(categoryTabColumn($columns, 'attr.wo_field')['editable'])->toBeTrue()
        ->and(array_column($this->getJson('/api/tables/work-orders/columns')->json('data.columns'), 'id'))->not->toContain('attr.wo_field');
});

it('reads work_orders.attribute_values, filters, sorts and lists distinct values (AC-004)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    $betaQuote = categoryTabQuote([$category]);
    $betaQuote->forceFill(['attribute_values' => ['wo_field' => 'from-quote']])->save();
    $beta = categoryTabMultiWorkOrder($betaQuote);
    $alpha = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $beta->forceFill(['attribute_values' => ['wo_field' => 'beta']])->save();
    $alpha->forceFill(['attribute_values' => ['wo_field' => 'alpha']])->save();
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $page = ['startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id];

    $rows = $this->postJson('/api/tables/work-orders/rows', $page)->assertOk()->json('items');
    expect(collect($rows)->mapWithKeys(fn (array $row): array => [$row['id'] => $row['attr.wo_field']])->all())
        ->toBe([$beta->id => 'beta', $alpha->id => 'alpha']);

    $sorted = $this->postJson('/api/tables/work-orders/rows', [...$page, 'sortModel' => [['colId' => 'attr.wo_field', 'sort' => 'asc']]])->json('items');
    expect(array_column($sorted, 'id'))->toBe([$alpha->id, $beta->id]);

    $filtered = $this->postJson('/api/tables/work-orders/rows', [...$page, 'filterModel' => ['attr.wo_field' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'alp']]])->json('items');
    expect(array_column($filtered, 'id'))->toBe([$alpha->id]);

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'attr.wo_field', 'productCategoryId' => $category->id])->assertOk()->json('data.values');
    expect($values)->toContain('alpha', 'beta')->not->toContain('from-quote');
});

it('writes on work_orders.attribute_values and leaves the offer untouched (AC-005)', function () {
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

it('refuses the write without permission and for an inapplicable code (AC-005)', function () {
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

it('accepts preferences and filters naming the attribute of any category (AC-008)', function () {
    categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'field_a']);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    $this->postJson('/api/tables/work-orders/preferences', ['columns' => [['id' => 'attr.field_a', 'visible' => false, 'order' => 1]]])->assertOk();
    $this->postJson('/api/tables/work-orders/filters', ['filterModel' => ['attr.field_a' => ['filterType' => 'text', 'type' => 'contains', 'filter' => 'x']]])->assertOk();
});

it('keeps a hidden attribute column hidden on reload (AC-008)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    Sanctum::actingAs(workOrderUserWith(['viewAny']));

    categoryTabAssertAttributeStaysHidden('work-orders', $category, 'wo_field');
});

it('shows rows and distinct values of the visible work orders only (AC-010)', function () {
    [$category] = categoryTabAttribute(AttributeContext::WorkOrder, 'text', ['code' => 'wo_field']);
    $actor = categoryTabGridActor('work-orders', viewAll: false);
    $mine = categoryTabMultiWorkOrder(categoryTabQuote([$category]));
    $mine->forceFill(['attribute_values' => ['wo_field' => 'visible-value']])->save();
    $mine->supervisors()->sync([$actor->id]);
    categoryTabMultiWorkOrder(categoryTabQuote([$category]))->forceFill(['attribute_values' => ['wo_field' => 'hidden-value']])->save();
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25, 'productCategoryId' => $category->id])->assertOk()->json('items');
    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'attr.wo_field', 'productCategoryId' => $category->id])->assertOk()->json('data.values');

    expect(array_column($rows, 'id'))->toBe([$mine->id])
        ->and($values)->toContain('visible-value')->not->toContain('hidden-value');
});
