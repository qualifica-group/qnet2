<?php

use App\Enums\TaskStatusGroup;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Delete authorization past Gate::before (spec 0125, D-1/D-2/D-5; spec 0153, D-5)
|--------------------------------------------------------------------------
|
| TaskService::delete() re-asserts TaskAbilityResolver::canDelete() exactly
| like approve/block already do, so the manageAll-as-assignee deroga (0116
| D-2) survives Gate::before. The UI flag, the grid row-action and the
| bulk-delete per-row authorization read the SAME rule.
|
| REQUIREMENT CHANGED (spec 0153, D-5): canDelete() moved off the mandate
| onto the SAME row as canUpdate() (creator/requester/assignee/manager),
| ANDed with the task's own open/unblocked STATE. A manageAll actor decayed
| to the assignee row (D-2) may therefore now delete an OPEN task like any
| other assignee — the tests below that used to prove "decay causes 403"
| are rewritten to prove the state veto applies just as evenly instead
| (AC-001/AC-002/AC-007), and the cascade guard on a foreign sub-task
| (AC-004) now answers 422, not 403/409.
|
| Spec 0126, D-1 rettifica: the super-admin itself no longer decays as an
| assignee (see TaskSuperAdminAssigneeTest AC-001).
*/

if (! function_exists('taskActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withViewAll) {
            $user->givePermissionTo('tasks.viewAll');
        }

        return $user;
    }
}

if (! function_exists('superAdminTaskActor')) {
    function superAdminTaskActor(): User
    {
        Role::findOrCreate('super-admin');
        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

beforeEach(function () {
    foreach (['viewAny', 'view', 'delete', 'viewAll', 'manageAll'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }
});

// ---------------------------------------------------------------------------
// AC-001/AC-002 — DELETE /api/tasks/{task}, the assignee row post-D-5
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-5): a manager (tasks.manageAll) decayed
// to the assignee row (D-2, since they are an assignee of THIS task) now
// deletes an OPEN task — the role decay no longer refuses delete, only the
// watcher role and the task's own state do.
it('AC-001 (spec 0153, D-5): a manager (tasks.manageAll) decayed to assignee deletes an open task', function () {
    $actor = taskActorWith(['view', 'delete', 'manageAll']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-5): the SAME decayed actor gets 403 on a
// CLOSED task — the state veto applies evenly, whatever the role.
it('AC-002 (spec 0153, D-5): the same decayed manager/assignee gets 403 on a closed task, task kept', function () {
    $actor = taskActorWith(['view', 'delete', 'manageAll']);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->inStatus($closed)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('AC-003: a super-admin assignee who is ALSO the creator deletes the task (roles sum, D-5)', function () {
    $actor = superAdminTaskActor();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

// REQUIREMENT CHANGED (spec 0153, D-5): cascade delete replaces the old
// 409-for-sub-tasks. The actor may delete the PARENT (assignee, open), but
// holds no role at all on the foreign child (deliberately WITHOUT
// `manageAll`, which would make them its resource-level manager instead) —
// the whole cascade aborts, 422 naming the child, neither row removed.
it('AC-004 (spec 0153, D-5): an assignee of a task WITH a foreign sub-task gets 422, cascade aborted', function () {
    $actor = taskActorWith(['view', 'delete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $foreignChild = Task::factory()->childOf($task)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('task');

    $this->assertDatabaseHas('tasks', ['id' => $task->id])
        ->assertDatabaseHas('tasks', ['id' => $foreignChild->id]);
});

// ---------------------------------------------------------------------------
// AC-005 — bulk-delete reports the per-row outcome
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-5): an assignee (here, on an open task)
// is no longer refused by role — only an actor with NO role at all on the
// OTHER task is reported forbidden.
it('AC-005: bulk-delete deletes the row the actor is an assignee of, reports the unrelated one as forbidden', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete']);
    $assigned = Task::factory()->create();
    $assigned->assignees()->attach($actor->id);
    $foreign = Task::factory()->create();
    Sanctum::actingAs($actor);

    $result = $this->postJson('/api/tables/tasks/bulk-delete', ['ids' => [$assigned->id, $foreign->id]])
        ->assertOk()
        ->json('data');

    expect($result['deleted'])->toBe(1);
    expect(collect($result['failed'])->firstWhere('id', $foreign->id)['reason'])->toBe('forbidden');
    $this->assertDatabaseMissing('tasks', ['id' => $assigned->id]);
    $this->assertDatabaseHas('tasks', ['id' => $foreign->id]);
});

// ---------------------------------------------------------------------------
// AC-006/AC-007 — permissions.actions.delete and the grid row-action
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-5): the assignee now sees TRUE on an
// open task — only the watcher stays FALSE regardless of state.
it('AC-006 (spec 0153, D-5): the assignee and the creator see permissions.actions.delete true on an open task, the watcher false', function () {
    $assignee = taskActorWith(['view', 'delete']);
    $watcher = taskActorWith(['view', 'delete']);
    $creator = taskActorWith(['view', 'delete']);
    $task = Task::factory()->forCreator($creator)->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);

    Sanctum::actingAs($assignee);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);

    Sanctum::actingAs($watcher);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);

    Sanctum::actingAs($creator);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);
});

// REQUIREMENT CHANGED (spec 0153, D-5): the STATE veto, not the role, is now
// what turns the flag/row-action off — a manager/assignee sees delete TRUE
// on an open task and FALSE on a closed one, whatever their role.
it('AC-007 (spec 0153, D-5): a manager/assignee has the delete flag and row-action on an open task, not on a closed one', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete', 'manageAll']);
    $open = Task::factory()->create();
    $open->assignees()->attach($actor->id);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $closedTask = Task::factory()->inStatus($closed)->create();
    $closedTask->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$open->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);
    $this->getJson("/api/tasks/{$closedTask->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);

    // Default `status` filter is `open` (D-1/D-2 of spec 0147/0153): lift it
    // explicitly so the closed Task's row is present to assert against.
    $rows = collect($this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0,
        'endRow' => 50,
        'advancedFilters' => ['status' => 'all'],
    ])->assertOk()->json('items'));
    expect($rows->firstWhere('id', $open->id)['actions'])->toContain('delete')
        ->and($rows->firstWhere('id', $closedTask->id)['actions'])->not->toContain('delete');
});
