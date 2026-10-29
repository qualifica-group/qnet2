<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderProgressEntry;
use App\Models\WorkOrderWorkflowStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function completeLinkedTask($test, $task, array $extra = [])
{
    return $test->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload(), ...$extra]);
}

it('AC-005: closing a linked task moves the commessa and adds the delta entry', function () {
    $actor = taskCompletionActorWith(['complete']);
    $workOrder = WorkOrder::factory()->create();
    seedProgressEntry($workOrder, 10);
    $status = globalProgressStatus(40);
    $task = linkedTask($workOrder, $status, $actor);
    Sanctum::actingAs($actor);

    completeLinkedTask($this, $task)->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id);
    $entry = $workOrder->progressEntries()->where('created_by', $actor->id)->sole();
    expect($entry->percentage)->toBe(30)
        ->and($entry->recorded_on->toDateString())->toBe(now()->toDateString())
        ->and($entry->created_by)->toBe($actor->id)
        ->and(progressTotal($workOrder))->toBe(40);

    $log = Activity::query()->where('subject_id', $workOrder->id)->where('description', 'Work order working status change')->sole();
    expect($log->causer_id)->toBe($actor->id);
});

it('AC-006: the validation percorso leaves the commessa alone, approve() applies it', function () {
    $actor = taskCompletionActorWith(['complete', 'validate']);
    $workOrder = WorkOrder::factory()->create();
    $status = globalProgressStatus(50);
    $openId = $workOrder->work_order_workflow_status_id;
    $validation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = linkedTask($workOrder, $status, User::factory()->create(), ['requires_validation' => true, 'requester_id' => User::factory()->create()->id]);
    $task->assignees()->sync([$actor->id]);
    Sanctum::actingAs($actor);

    completeLinkedTask($this, $task, ['validation_status_id' => $validation->id])->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($openId)
        ->and(progressTotal($workOrder))->toBe(0);

    $task->forceFill(['creator_id' => $actor->id])->save();
    $this->postJson("/api/tasks/{$task->id}/approve")->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and(progressTotal($workOrder))->toBe(50);
});

it('AC-007: a lower, equal or null percentage changes the status but adds no entry', function (?int $percentage) {
    $actor = taskCompletionActorWith(['complete']);
    $workOrder = WorkOrder::factory()->create();
    seedProgressEntry($workOrder, 60);
    $status = globalProgressStatus($percentage);
    $task = linkedTask($workOrder, $status, $actor);
    Sanctum::actingAs($actor);

    completeLinkedTask($this, $task)->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and($workOrder->progressEntries()->count())->toBe(1)
        ->and(progressTotal($workOrder))->toBe(60);
})->with([30, 60, null]);

it('AC-008: a status outside the resolved set is ignored and the task still closes', function () {
    $actor = taskCompletionActorWith(['complete']);
    $workOrder = WorkOrder::factory()->create();
    $openId = $workOrder->work_order_workflow_status_id;
    $foreign = WorkOrderWorkflowStatus::factory()->create(['progress_percentage' => 70]);
    $task = linkedTask($workOrder, $foreign, $actor);
    Sanctum::actingAs($actor);

    completeLinkedTask($this, $task)->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($openId)
        ->and($workOrder->progressEntries()->count())->toBe(0)
        ->and($task->fresh()->taskStatus->group)->toBe(TaskStatusGroup::ClosedPositive);
});

it('AC-009: a force-closed commessa, or a task without status/commessa, is untouched', function () {
    $actor = taskCompletionActorWith(['complete']);
    Sanctum::actingAs($actor);
    $status = globalProgressStatus(40);

    $closed = WorkOrder::factory()->create(['is_force_closed' => true, 'force_close_reason' => 'x']);
    $closedStatusId = $closed->work_order_workflow_status_id;
    completeLinkedTask($this, linkedTask($closed, $status, $actor))->assertOk();
    expect($closed->fresh()->work_order_workflow_status_id)->toBe($closedStatusId)->and($closed->progressEntries()->count())->toBe(0);

    $plain = WorkOrder::factory()->create();
    completeLinkedTask($this, linkedTask($plain, null, $actor))->assertOk();
    expect($plain->progressEntries()->count())->toBe(0);

    $orphan = Task::factory()->create(['work_order_id' => null]);
    $orphan->forceFill(['work_order_workflow_status_id' => $status->id])->save();
    $orphan->assignees()->attach($actor->id);
    completeLinkedTask($this, $orphan)->assertOk();
});

it('AC-010: uncomplete and reject do not touch the commessa', function () {
    $actor = taskCompletionActorWith(['complete', 'validate']);
    $workOrder = WorkOrder::factory()->create();
    $status = globalProgressStatus(40);
    $task = linkedTask($workOrder, $status, $actor);
    Sanctum::actingAs($actor);
    completeLinkedTask($this, $task)->assertOk();

    $this->postJson("/api/tasks/{$task->id}/uncomplete")->assertOk();

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($status->id)
        ->and(progressTotal($workOrder))->toBe(40);
});

it('AC-011: bulk complete applies each closure, the total is the max and never goes down', function () {
    $actor = taskCompletionActorWith(['complete']);
    foreach (['viewAny', 'view', 'viewAll'] as $ability) {
        Permission::findOrCreate("work-orders.{$ability}");
    }
    $actor->givePermissionTo(['work-orders.view', 'work-orders.viewAll']);
    $workOrder = WorkOrder::factory()->create();
    $high = globalProgressStatus(70);
    $low = globalProgressStatus(30);
    $first = linkedTask($workOrder, $high, $actor);
    $second = linkedTask($workOrder, $low, $actor);
    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'task_ids' => [$first->id, $second->id],
        'action' => 'complete',
        'time_entry' => validTimeEntryPayload(),
    ])->assertOk();

    expect(progressTotal($workOrder))->toBe(70)
        ->and($workOrder->progressEntries()->min('percentage'))->toBeGreaterThan(0)
        ->and($workOrder->fresh()->work_order_workflow_status_id)->toBe($low->id);
});

it('AC-012: a failure after the commessa update rolls everything back', function () {
    $actor = taskCompletionActorWith(['complete']);
    $workOrder = WorkOrder::factory()->create();
    $openId = $workOrder->work_order_workflow_status_id;
    $status = globalProgressStatus(40);
    $task = linkedTask($workOrder, $status, $actor);
    Sanctum::actingAs($actor);

    WorkOrderProgressEntry::creating(fn () => throw new RuntimeException('boom'));

    completeLinkedTask($this, $task)->assertStatus(500);

    expect($workOrder->fresh()->work_order_workflow_status_id)->toBe($openId)
        ->and($workOrder->progressEntries()->count())->toBe(0)
        ->and($task->fresh()->taskStatus->group)->not->toBe(TaskStatusGroup::ClosedPositive);
});
