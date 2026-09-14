<?php

use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Delete authorization past Gate::before (spec 0125, D-1/D-2/D-5)
|--------------------------------------------------------------------------
|
| TaskService::delete() re-asserts TaskAbilityResolver::canDelete() exactly
| like approve/block already do, so the manageAll-as-assignee deroga (0116
| D-2) survives Gate::before. The UI flag, the grid row-action and the
| bulk-delete per-row authorization read the SAME rule.
|
| Spec 0126, D-1 rettifica: the super-admin itself no longer decays as an
| assignee (see TaskSuperAdminAssigneeTest AC-001). AC-001/AC-004/AC-005/
| AC-007 below used to exercise that decay on a super-admin actor
| specifically (the "past Gate::before" proof); they are rewritten on a
| genuine `tasks.manageAll` (non-super-admin) actor, which still decays.
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
// AC-001..AC-004 — DELETE /api/tasks/{task}
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0126, D-1): a super-admin assignee no longer
// gets 403 here (see TaskSuperAdminAssigneeTest AC-001, 204 now). The decay
// this test proves stays true for an ORDINARY `tasks.manageAll` actor, so
// the actor here is rewritten as one instead of a super-admin.
it('AC-001: a manager (tasks.manageAll, not super-admin) who is an assignee (not creator/requester) gets 403 on DELETE, task kept', function () {
    $actor = taskActorWith(['view', 'delete', 'manageAll']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('AC-002: an actor with tasks.delete and tasks.manageAll who is an assignee gets 403 on DELETE, task kept', function () {
    $actor = taskActorWith(['view', 'delete', 'manageAll']);
    $task = Task::factory()->create();
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

// REQUIREMENT CHANGED (spec 0126, D-1): a super-admin assignee no longer
// decays; rewritten on an ORDINARY `tasks.manageAll` actor, which still does.
it('AC-004: a manager (tasks.manageAll, not super-admin) assignee of a task WITH sub-tasks gets 403, not 409', function () {
    $actor = taskActorWith(['view', 'delete', 'manageAll']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Task::factory()->childOf($task)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

// ---------------------------------------------------------------------------
// AC-005 — bulk-delete reports the row as forbidden
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0126, D-1): a super-admin assignee no longer
// decays; rewritten on an ORDINARY `tasks.manageAll` actor, which still does.
it('AC-005: bulk-delete by a manager (tasks.manageAll, not super-admin) assignee reports the task as forbidden and keeps it', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete', 'manageAll']);
    $assigned = Task::factory()->create();
    $assigned->assignees()->attach($actor->id);
    $foreign = Task::factory()->create();
    Sanctum::actingAs($actor);

    $result = $this->postJson('/api/tables/tasks/bulk-delete', ['ids' => [$assigned->id, $foreign->id]])
        ->assertOk()
        ->json('data');

    expect($result['deleted'])->toBe(1);
    expect(collect($result['failed'])->firstWhere('id', $assigned->id)['reason'])->toBe('forbidden');
    $this->assertDatabaseHas('tasks', ['id' => $assigned->id]);
    $this->assertDatabaseMissing('tasks', ['id' => $foreign->id]);
});

// ---------------------------------------------------------------------------
// AC-006/AC-007 — permissions.actions.delete and the grid row-action
// ---------------------------------------------------------------------------

it('AC-006: assignee and watcher holding tasks.delete see permissions.actions.delete false, the creator true', function () {
    $assignee = taskActorWith(['view', 'delete']);
    $watcher = taskActorWith(['view', 'delete']);
    $creator = taskActorWith(['view', 'delete']);
    $task = Task::factory()->forCreator($creator)->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);

    Sanctum::actingAs($assignee);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);

    Sanctum::actingAs($watcher);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);

    Sanctum::actingAs($creator);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);
});

// REQUIREMENT CHANGED (spec 0126, D-1): a super-admin assignee no longer
// decays; rewritten on ORDINARY `tasks.manageAll` actors, which still do.
it('AC-007: a manager (tasks.manageAll, not super-admin) assignee has no delete flag nor delete row-action; a non-assignee manager has both', function () {
    $assignedManager = taskActorWith(['viewAny', 'view', 'delete', 'manageAll']);
    $otherManager = taskActorWith(['viewAny', 'view', 'delete', 'manageAll']);
    $task = Task::factory()->create();
    $task->assignees()->attach($assignedManager->id);

    Sanctum::actingAs($assignedManager);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', false);
    $row = collect($this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->firstWhere('id', $task->id);
    expect($row['actions'])->not->toContain('delete');

    Sanctum::actingAs($otherManager);
    $this->getJson("/api/tasks/{$task->id}")->assertOk()->assertJsonPath('permissions.actions.delete', true);
    $row = collect($this->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50])->assertOk()->json('items'))
        ->firstWhere('id', $task->id);
    expect($row['actions'])->toContain('delete');
});
