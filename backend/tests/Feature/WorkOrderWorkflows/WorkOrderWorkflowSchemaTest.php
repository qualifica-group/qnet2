<?php

use App\Models\WorkOrder;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// AC-001 — global default set + factory-made work orders sit on its `open` row.
it('seeds the global default set with open first and completed/closed in the tail', function () {
    $set = WorkOrderWorkflowStatus::query()->whereNull('work_order_workflow_id')->orderBy('sort_order')->get();

    expect($set->pluck('system_key')->all())->toBe(['open', 'completed', 'closed'])
        ->and($set->pluck('name')->all())->toBe(['Aperta', 'Completata', 'Chiusa'])
        ->and($set->pluck('sort_order')->all())->toBe([0, 10, 20])
        ->and($set->map(fn ($status) => $status->group->value)->all())->toBe(['open', 'completed', 'closed'])
        ->and($set->every->isSystem())->toBeTrue();
});

it('lands a factory-made work order on the global open status', function () {
    $workOrder = WorkOrder::factory()->create();

    expect($workOrder->work_order_workflow_status_id)->toBe(globalWorkOrderStatus('open')->id);
});

it('keeps system_key, workflow id and old_id out of mass assignment', function () {
    $status = new WorkOrderWorkflowStatus;
    $status->fill(['name' => 'X', 'system_key' => 'open', 'work_order_workflow_id' => 9, 'old_id' => 7, 'progress_percentage' => 40]);

    expect($status->system_key)->toBeNull()
        ->and($status->work_order_workflow_id)->toBeNull()
        ->and($status->old_id)->toBeNull()
        ->and($status->progress_percentage)->toBe(40);
});

it('refuses to delete a status a work order still points at (restrictOnDelete)', function () {
    $workOrder = WorkOrder::factory()->create();

    expect(fn () => DB::table('work_order_workflow_statuses')->where('id', $workOrder->work_order_workflow_status_id)->delete())
        ->toThrow(QueryException::class);
});
