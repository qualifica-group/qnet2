<?php

use App\Models\BusinessFunction;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflow;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A work order classified by $function (one REVENUE line) and assigned to its
 * workflow's open row, next to the plain global-set one.
 *
 * @return array{0: WorkOrder, 1: WorkOrder, 2: WorkOrderWorkflow}
 */
function workOrdersOnTwoSets(): array
{
    $function = BusinessFunction::factory()->create();
    $category = ProductCategory::factory()->create(['business_function_id' => $function->id]);
    $workflow = workOrderWorkflowFor('Grid set', ['business_function_id' => $function->id], ['Working']);

    $classified = workOrderWithRevenueIn([$category]);
    $classified->forceFill(['work_order_workflow_status_id' => $workflow->statuses()->where('system_key', 'open')->value('id')])->save();

    return [$classified, WorkOrder::factory()->create(), $workflow];
}

// AC-012
it('rows: the cell carries id/name/color and the options are the ids of the row own set', function () {
    [$classified, $plain, $workflow] = workOrdersOnTwoSets();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));

    $rows = collect($this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk()->json('items'));
    $classifiedRow = $rows->firstWhere('id', $classified->id);
    $plainRow = $rows->firstWhere('id', $plain->id);

    expect(array_keys($classifiedRow['work_order_workflow_status']))->toBe(['id', 'name', 'color'])
        ->and($classifiedRow['work_order_workflow_status']['id'])->toBe($classified->work_order_workflow_status_id)
        ->and($classifiedRow['work_order_workflow_status_options'])->toBe($workflow->statuses()->orderBy('sort_order')->pluck('id')->all())
        ->and($plainRow['work_order_workflow_status']['id'])->toBe(globalWorkOrderStatus('open')->id)
        ->and($plainRow['work_order_workflow_status_options'])->toBe(
            WorkOrderWorkflowStatus::query()->whereNull('work_order_workflow_id')->orderBy('sort_order')->pluck('id')->all(),
        );
});

it('rows: resolving the options costs no query per row', function () {
    workOrdersOnTwoSets();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));

    // Warm-up: permission and config caches are filled by the first request only.
    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    DB::enableQueryLog();
    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $few = count(DB::getQueryLog());

    foreach (range(1, 6) as $_) {
        workOrderWithRevenueIn([ProductCategory::factory()->create()]);
    }
    DB::flushQueryLog();
    $this->postJson('/api/tables/work-orders/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    expect(count(DB::getQueryLog()))->toBe($few);
});

it('columns: the status column is an inline select editor whose catalogue carries the color', function () {
    workOrdersOnTwoSets();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $column = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))
        ->firstWhere('id', 'work_order_workflow_status');

    expect($column['editable'])->toBeTrue()
        ->and($column['editor'])->toBe('select')
        ->and(array_keys($column['options'][0]))->toBe(['value', 'label', 'color'])
        ->and(collect($column['options'])->pluck('label')->all())->toContain('Working');
});

it('filter: returns only the rows on the requested status names', function () {
    [$classified, $plain] = workOrdersOnTwoSets();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    $filtered = fn (array $values): array => collect($this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['work_order_workflow_status' => ['filterType' => 'set', 'values' => $values]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    $working = WorkOrderWorkflowStatus::query()->where('name', 'Working')->sole();
    $classified->forceFill(['work_order_workflow_status_id' => $working->id])->save();

    expect($filtered(['Working']))->toBe([$classified->id])
        ->and($filtered(['Aperta', 'Working']))->toEqualCanonicalizing([$classified->id, $plain->id])
        ->and($filtered(['Nonexistent']))->toBe([]);
});

it('sort: orders by the status name and distinct values list the names in use', function () {
    [$classified, $plain] = workOrdersOnTwoSets();
    $classified->forceFill(['work_order_workflow_status_id' => WorkOrderWorkflowStatus::query()->where('name', 'Working')->value('id')])->save();
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));

    $ids = fn (string $direction): array => collect($this->postJson('/api/tables/work-orders/rows', [
        'startRow' => 0, 'endRow' => 25, 'sortModel' => [['colId' => 'work_order_workflow_status', 'sort' => $direction]],
    ])->assertOk()->json('items'))->pluck('id')->all();

    expect($ids('desc'))->toBe([$classified->id, $plain->id])
        ->and($ids('asc'))->toBe([$plain->id, $classified->id]);

    $values = $this->postJson('/api/tables/work-orders/values', ['columnId' => 'work_order_workflow_status'])->assertOk()->json('data.values');
    expect($values)->toEqual(['Aperta', 'Working']);
});

it('inline write: a status of the row set is saved, one outside it is rejected, a missing update permission is 403', function () {
    [$classified, , $workflow] = workOrdersOnTwoSets();
    $working = $workflow->statuses()->where('name', 'Working')->sole();
    $foreign = workOrderWorkflowFor('Other', ['business_function_id' => BusinessFunction::factory()->create()->id])
        ->statuses()->where('system_key', 'open')->value('id');

    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view']));
    $this->patchJson("/api/tables/work-orders/rows/{$classified->id}", ['column' => 'work_order_workflow_status', 'value' => $working->id])
        ->assertForbidden();

    Sanctum::actingAs(workOrderUserWith(['viewAny', 'view', 'update']));
    $this->patchJson("/api/tables/work-orders/rows/{$classified->id}", ['column' => 'work_order_workflow_status', 'value' => $foreign])
        ->assertStatus(422);

    $this->patchJson("/api/tables/work-orders/rows/{$classified->id}", ['column' => 'work_order_workflow_status', 'value' => $working->id])
        ->assertOk();

    expect($classified->fresh()->work_order_workflow_status_id)->toBe($working->id);
});
