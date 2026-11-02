<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task board bulk actions (spec 0146, BE-5, D-7, AC-017..AC-020)
|--------------------------------------------------------------------------
*/

if (! function_exists('taskBulkActor')) {
    /**
     * `tasks.manageAll` is DELIBERATELY not granted: it would make
     * `TaskAbilityResolver::isManager()` true for every Task regardless of
     * role, which defeats the "unauthorized task" half of AC-017 — every
     * other bulk test relies on `forCreator($actor)` for ownership instead,
     * so nothing else here needs it.
     */
    function taskBulkActor(): User
    {
        $actor = workOrderUserWith(['view']);

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        foreach (['viewAny', 'view', 'update', 'complete', 'block', 'viewAll'] as $ability) {
            $actor->givePermissionTo("tasks.{$ability}");
        }

        return $actor;
    }
}

it('bulk assign: replaces assignees on each authorized task, an unauthorized one fails without blocking the rest (AC-017)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $newAssignee = User::factory()->create();

    $ownTask = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $foreignTask = Task::factory()->create(['work_order_id' => $workOrder->id]); // actor has no role on it

    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'assign',
        'task_ids' => [$ownTask->id, $foreignTask->id],
        'assignee_ids' => [$newAssignee->id],
    ])->assertOk();

    expect($response->json('data.succeeded'))->toBe(1)
        ->and($response->json('data.failed'))->toBe(1);

    $ownResult = collect($response->json('data.results'))->firstWhere('task_id', $ownTask->id);
    $foreignResult = collect($response->json('data.results'))->firstWhere('task_id', $foreignTask->id);

    expect($ownResult['ok'])->toBeTrue()
        ->and($foreignResult['ok'])->toBeFalse()
        ->and($foreignResult['message'])->not->toBeNull();

    $this->assertDatabaseHas('task_assignee', ['task_id' => $ownTask->id, 'user_id' => $newAssignee->id]);
});

// REQUIREMENT CHANGED (spec 0338, D-5, replaces spec 0146 AC-018): `complete` is
// all-or-nothing on the board, like POST /api/tasks/bulk.
it('bulk complete is all-or-nothing: one blocked, validation-required or open-subtask task makes it 422 with nothing written (spec 0338 AC-006)', function () {
    Notification::fake();
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();

    $simple = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);

    $requiringValidation = Task::factory()->requiringValidation()->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);
    $requiringValidation->assignees()->attach($actor->id);

    $blocked = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id, 'is_blocked' => true]);

    $withOpenSubtask = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);
    Task::factory()->inStatus($openStatus)->childOf($withOpenSubtask)->create();

    Sanctum::actingAs($actor);

    $ids = [$simple->id, $requiringValidation->id, $blocked->id, $withOpenSubtask->id];

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'complete',
        'task_ids' => $ids,
        'time_entry' => validTimeEntryPayload(['minutes' => 40]),
        'time_entry_shares' => collect($ids)->map(fn (int $id) => ['task_id' => $id, 'minutes' => 10])->all(),
    ])->assertStatus(422)->assertJsonPath('success', false);

    $incompatible = collect($response->json('incompatible_tasks'))->keyBy('id');
    expect($incompatible->keys()->sort()->values()->all())->toBe(collect([$requiringValidation->id, $blocked->id, $withOpenSubtask->id])->sort()->values()->all())
        ->and($incompatible[$blocked->id]['reason'])->not->toBeEmpty()
        ->and($response->json('errors.task_ids'))->not->toBeEmpty();

    $this->assertDatabaseCount('time_entries', 0);
    $this->assertDatabaseHas('tasks', ['id' => $simple->id, 'task_status_id' => $openStatus->id]);
});

it('bulk complete without for_all_assignees logs the time entry for the actor only (spec 0319 AC-001)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $otherAssignee = User::factory()->create();

    $task = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'complete',
        'task_ids' => [$task->id],
        // REQUIREMENT CHANGED (spec 0338): time_entry.minutes is the TOTAL, split via time_entry_shares.
        'time_entry' => validTimeEntryPayload(),
        'time_entry_shares' => [['task_id' => $task->id, 'minutes' => 60]],
    ])->assertOk();

    $this->assertDatabaseCount('time_entries', 1);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id]);
});

it('bulk complete with for_all_assignees true logs one time entry per assignee (spec 0319 AC-002)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $otherAssignee = User::factory()->create();

    $task = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'complete',
        'task_ids' => [$task->id],
        // REQUIREMENT CHANGED (spec 0338): time_entry.minutes is the TOTAL, split via time_entry_shares.
        'time_entry' => validTimeEntryPayload(),
        'time_entry_shares' => [['task_id' => $task->id, 'minutes' => 60]],
        'for_all_assignees' => true,
    ])->assertOk();

    $this->assertDatabaseCount('time_entries', 2);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $actor->id]);
    $this->assertDatabaseHas('time_entries', ['task_id' => $task->id, 'user_id' => $otherAssignee->id]);
});

it('rejects for_all_assignees on a bulk action other than complete (spec 0319 AC-003)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block',
        'task_ids' => [$task->id],
        'for_all_assignees' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors(['for_all_assignees']);
});

it('bulk uncomplete/block/priority apply through the existing domain services (AC-019)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $closedStatus = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $priority = TaskPriority::factory()->create(['is_active' => true]);

    $toReopen = Task::factory()->forCreator($actor)->inStatus($closedStatus)->create(['work_order_id' => $workOrder->id, 'completion_date' => '2026-09-20']);
    $toBlock = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);
    $toRepriority = Task::factory()->forCreator($actor)->inStatus($openStatus)->create(['work_order_id' => $workOrder->id]);

    Sanctum::actingAs($actor);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'uncomplete', 'task_ids' => [$toReopen->id],
    ])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->assertDatabaseHas('tasks', ['id' => $toReopen->id, 'is_blocked' => false, 'completion_date' => null]);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => [$toBlock->id],
    ])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->assertDatabaseHas('tasks', ['id' => $toBlock->id, 'is_blocked' => true]);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'priority', 'task_ids' => [$toRepriority->id], 'task_priority_id' => $priority->id,
    ])->assertOk()->assertJsonPath('data.succeeded', 1);
    $this->assertDatabaseHas('tasks', ['id' => $toRepriority->id, 'task_priority_id' => $priority->id]);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'dates', 'task_ids' => [$toRepriority->id], 'start_date' => '2026-10-01', 'end_date' => '2026-09-01',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');
});

it('bulk: more than 200 ids, duplicate ids, or a non-root id 422 (AC-020)', function () {
    $actor = taskBulkActor();
    $workOrder = WorkOrder::factory()->create();
    $root = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);
    $subtask = Task::factory()->forCreator($actor)->childOf($root)->create(['work_order_id' => $workOrder->id]);
    Sanctum::actingAs($actor);

    $tooMany = range(1, 201);
    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => $tooMany,
    ])->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => [$root->id, $root->id],
    ])->assertStatus(422);

    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => [$subtask->id],
    ])->assertStatus(422);
});

it('bulk: 403 without view on the commessa, 409 once it is closed', function () {
    // Both actors (and every Permission::findOrCreate() row they need) are
    // built BEFORE either Sanctum::actingAs() call: Sanctum's actingAs()
    // switches the app's default auth guard for the REST of the test
    // (Auth::shouldUse('sanctum')), and Spatie's own guard resolution falls
    // back to that default for a permission row created with no explicit
    // guard — building an actor's permissions AFTER an earlier actingAs()
    // in the same test would silently create it under the wrong guard.
    $actor = workOrderUserWith([]);
    $manager = taskBulkActor();

    $workOrder = WorkOrder::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['work_order_id' => $workOrder->id]);

    $closedWorkOrder = WorkOrder::factory()->forceClosed()->create();
    $closedTask = Task::factory()->forCreator($manager)->create(['work_order_id' => $closedWorkOrder->id]);

    Sanctum::actingAs($actor);
    $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => [$task->id],
    ])->assertForbidden();

    Sanctum::actingAs($manager);
    $this->postJson("/api/work-orders/{$closedWorkOrder->id}/task-board/bulk", [
        'action' => 'block', 'task_ids' => [$closedTask->id],
    ])->assertStatus(409);
});
