<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Spec 0126, D-1 — the super-admin assignee no longer decays (AC-001..AC-003)
|--------------------------------------------------------------------------
|
| RETTIFICA of 0116 D-2 and 0125 D-1/D-5: `TaskRecordRoles::isManager()` now
| short-circuits true for a super-admin regardless of assignee status (the
| privileged role no longer falls back to the ordinary manageAll-as-assignee
| deroga). A `tasks.manageAll` actor who is NOT super-admin keeps decaying
| exactly as before. This file is the single place asserting the NEW
| super-admin-allowed half (AC-001/AC-002); the decay half for a genuine
| `tasks.manageAll` actor (AC-003 here, and the pre-existing coverage in
| TaskActionsTest, TaskRequestUpdateTest, TaskDeleteAuthorizationTest,
| TaskRecordRoleMatrixTest) is unaffected by this decision and stays where
| it always was.
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

if (! function_exists('validTimeEntryPayload')) {
    /**
     * A valid `time_entry` (spec 0123, D-1: mandatory on every /complete
     * call, regardless of what this suite exercises).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function validTimeEntryPayload(array $overrides = []): array
    {
        return array_merge([
            'date' => '2026-09-14',
            'task_type_id' => TaskType::factory()->create()->id,
            'minutes' => 60,
        ], $overrides);
    }
}

if (! function_exists('closedPositiveStatusId')) {
    function closedPositiveStatusId(): int
    {
        return TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->value('id');
    }
}

// ---------------------------------------------------------------------------
// AC-001 — a super-admin assignee (not creator/requester) is admin, not assignee
// ---------------------------------------------------------------------------

it('AC-001: a super-admin assignee deletes the task, no roles owed', function () {
    $actor = superAdminTaskActor();
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertNoContent();

    $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
});

it('AC-001: a super-admin assignee approves a task awaiting validation', function () {
    $actor = superAdminTaskActor();
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', closedPositiveStatusId());

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => closedPositiveStatusId()]);
});

it('AC-001: a super-admin assignee blocks the task', function () {
    $actor = superAdminTaskActor();
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")
        ->assertOk()
        ->assertJsonPath('data.is_blocked', true);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => true]);
});

it('AC-001: a super-admin assignee requests an update with valid recipients', function () {
    $actor = superAdminTaskActor();
    $recipient = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $recipient->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", [
        'target' => 'assignees',
        'message' => 'Serve un aggiornamento.',
    ])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-002 — a super-admin assignee on a flagged Task closes directly, no in_validation
// ---------------------------------------------------------------------------

it('AC-002: a super-admin assignee completing a task requiring validation closes it directly', function () {
    $actor = superAdminTaskActor();
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', closedPositiveStatusId())
        ->assertJsonPath('data.completion_date', now()->toDateString());

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => closedPositiveStatusId()]);
});

// ---------------------------------------------------------------------------
// AC-003 — a genuine tasks.manageAll actor (NOT super-admin) still decays
// ---------------------------------------------------------------------------

it('AC-003: a tasks.manageAll (non super-admin) assignee gets 403 on delete/approve/block/request-update', function () {
    $actor = taskActorWith(['delete', 'validate', 'block', 'requestUpdate', 'manageAll']);
    $otherAssignee = User::factory()->create();
    Sanctum::actingAs($actor);

    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $approveTask = Task::factory()->inStatus($inValidation)->create();
    $approveTask->assignees()->attach($actor->id);
    $this->postJson("/api/tasks/{$approveTask->id}/approve")->assertStatus(403);
    $this->assertDatabaseHas('tasks', ['id' => $approveTask->id, 'task_status_id' => $inValidation->id]);

    $blockTask = Task::factory()->create();
    $blockTask->assignees()->attach($actor->id);
    $this->postJson("/api/tasks/{$blockTask->id}/block")->assertStatus(403);
    $this->assertDatabaseHas('tasks', ['id' => $blockTask->id, 'is_blocked' => false]);

    $requestUpdateTask = Task::factory()->create();
    $requestUpdateTask->assignees()->attach([$actor->id, $otherAssignee->id]);
    $this->postJson("/api/tasks/{$requestUpdateTask->id}/request-update", [
        'target' => 'assignees',
        'message' => 'Serve un aggiornamento.',
    ])->assertStatus(403);

    // REQUIREMENT CHANGED (spec 0153, D-5): decaying to the assignee row no
    // longer refuses delete on an OPEN task (an assignee may now delete
    // one) — the closed state here is what still answers 403.
    $closedPositive = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $deleteTask = Task::factory()->inStatus($closedPositive)->create();
    $deleteTask->assignees()->attach($actor->id);
    $this->deleteJson("/api/tasks/{$deleteTask->id}")->assertStatus(403);
    $this->assertDatabaseHas('tasks', ['id' => $deleteTask->id]);
});

it('AC-003: permissions.actions.delete/approve/block are false for a tasks.manageAll (non super-admin) assignee', function () {
    $actor = taskActorWith(['view', 'delete', 'validate', 'block', 'manageAll']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.approve', false)
        ->assertJsonPath('permissions.actions.block', false);
});
