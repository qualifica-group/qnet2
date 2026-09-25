<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task board bulk actions (spec 0146, BE-5, D-7, AC-017..AC-020)
|--------------------------------------------------------------------------
*/

if (! function_exists('workOrderUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workOrderUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
            Permission::findOrCreate("work-orders.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("work-orders.{$ability}");
        }

        $user->givePermissionTo('work-orders.viewAll');

        return $user;
    }
}

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

it('bulk complete: closes completable tasks and logs a time entry each; validation-required, blocked and open-subtask tasks fail without partial writes (AC-018)', function () {
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

    $response = $this->postJson("/api/work-orders/{$workOrder->id}/task-board/bulk", [
        'action' => 'complete',
        'task_ids' => [$simple->id, $requiringValidation->id, $blocked->id, $withOpenSubtask->id],
        'time_entry' => validTimeEntryPayload(),
    ])->assertOk();

    expect($response->json('data.succeeded'))->toBe(1)
        ->and($response->json('data.failed'))->toBe(3);

    $results = collect($response->json('data.results'))->keyBy('task_id');
    expect($results[$simple->id]['ok'])->toBeTrue()
        ->and($results[$requiringValidation->id]['ok'])->toBeFalse()
        ->and($results[$blocked->id]['ok'])->toBeFalse()
        ->and($results[$withOpenSubtask->id]['ok'])->toBeFalse();

    $this->assertDatabaseHas('time_entries', ['task_id' => $simple->id]);
    $this->assertDatabaseMissing('time_entries', ['task_id' => $requiringValidation->id]);
    $this->assertDatabaseHas('tasks', ['id' => $blocked->id, 'is_blocked' => true]);
    $this->assertDatabaseHas('tasks', ['id' => $withOpenSubtask->id, 'task_status_id' => $openStatus->id]);
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
