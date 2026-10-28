<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;

// Spec 0206: the work-orders grid edits its cells through the form's own
// UpdateWorkOrderRequest + WorkOrderService (WorkOrderCellWriter).

uses(RefreshDatabase::class);

function patchWorkOrderCell(WorkOrder $workOrder, string $column, mixed $value): TestResponse
{
    return test()->patchJson("/api/tables/work-orders/rows/{$workOrder->id}", ['column' => $column, 'value' => $value]);
}

it('declares exactly the columns the form edits as single fields', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));

    $editable = collect($this->getJson('/api/tables/work-orders/columns')->assertOk()->json('data.columns'))
        ->where('editable', true)->pluck('id')->sort()->values()->all();

    // REQUIREMENT CHANGED (spec 0322): the "Stato lavorazione" select joins the inline-editable columns.
    expect($editable)->toBe(['callback_date', 'start_date', 'supervisors', 'title', 'type', 'work_order_workflow_status']);
});

it('saves title, type, dates and supervisors through the form path', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $workOrder = WorkOrder::factory()->create(['type' => 'processing']);
    $first = User::factory()->create();
    $second = User::factory()->create();

    patchWorkOrderCell($workOrder, 'title', 'Commessa rinominata')->assertOk()->assertJsonPath('data.title', 'Commessa rinominata');
    patchWorkOrderCell($workOrder, 'type', 'project')->assertOk()->assertJsonPath('data.type', 'project');
    patchWorkOrderCell($workOrder, 'start_date', '2026-11-02')->assertOk();
    patchWorkOrderCell($workOrder, 'callback_date', null)->assertOk();
    patchWorkOrderCell($workOrder, 'supervisors', [$first->id, $second->id])->assertOk();

    $fresh = $workOrder->fresh();
    expect($fresh->start_date->toDateString())->toBe('2026-11-02')
        ->and($fresh->callback_date)->toBeNull()
        ->and($fresh->supervisors->pluck('id')->sort()->values()->all())->toBe([$first->id, $second->id]);
});

it('applies the form rules: a title over the form maximum is refused', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $workOrder = WorkOrder::factory()->create(['title' => 'Originale']);

    patchWorkOrderCell($workOrder, 'title', str_repeat('x', 192))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('title');

    expect($workOrder->fresh()->title)->toBe('Originale');
});

// REQUIREMENT CHANGED (user decision 2026-10-20): the supervisors may be cleared.
it('clears the supervisors and refuses an unknown type', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->sync([User::factory()->create()->id]);

    patchWorkOrderCell($workOrder, 'supervisors', [])->assertOk();
    patchWorkOrderCell($workOrder, 'type', 'unknown')->assertUnprocessable();

    expect($workOrder->fresh()->supervisors)->toBeEmpty();
});

it('keeps force-closing a row action, never a cell', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny', 'update']));
    $workOrder = WorkOrder::factory()->create();

    patchWorkOrderCell($workOrder, 'is_force_closed', true)->assertUnprocessable();

    expect($workOrder->fresh()->is_force_closed)->toBeFalse();
});

it('forbids the write without work-orders.update', function () {
    Sanctum::actingAs(workOrderUserWith(['viewAny']));
    $workOrder = WorkOrder::factory()->create(['title' => 'Originale']);

    patchWorkOrderCell($workOrder, 'title', 'Tentativo')->assertForbidden();

    expect($workOrder->fresh()->title)->toBe('Originale');
});
