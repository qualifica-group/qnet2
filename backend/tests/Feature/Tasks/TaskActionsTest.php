<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Defense against the helper-collision trap: `taskActorWith()` is declared
// once per process (bare functions are global) and PHP keeps whichever
// `tests/Feature/Tasks/*.php` copy loads FIRST, alphabetically. That copy
// may predate spec 0116 and not know these four permissions yet, so a
// `givePermissionTo('tasks.complete')` downstream would throw
// PermissionDoesNotExist — but only when the suite runs as a whole, a
// verdict that depends on load order. Creating them here directly is
// idempotent and makes this file correct regardless of which copy is
// active, the same defense TaskRecordRoleMatrixTest already adopted.
beforeEach(function () {
    foreach (['manageAll', 'complete', 'validate', 'block'] as $ability) {
        Permission::findOrCreate("tasks.{$ability}");
    }
});

/*
|--------------------------------------------------------------------------
| The six domain-action endpoints (spec 0116, MT-04, AC-016..AC-030, AC-011)
|--------------------------------------------------------------------------
|
| complete/uncomplete/approve/reject/block/unblock, served by
| App\Services\Tasks\TaskActionService behind TaskPolicy's
| complete/validate/block abilities. The record-role matrix itself
| (TaskAbilityResolver) and the ability/matrix AND-not-OR contract on
| `permissions.actions` are TaskRecordRoleMatrixTest's territory; this suite
| exercises the endpoints' own three re-asserted guards (D-2 manager-as-
| assignee deroga, D-8 is_blocked veto — spec 0153, D-7: now only
| complete/approve, uncomplete/reject LIFT the block instead —,
| TaskActionAvailability) plus the transitions' target statuses.
*/

if (! function_exists('taskActorWith')) {
    /**
     * An actor holding $abilities on `tasks`. `viewAll` is granted on top by
     * default so a 403 here always means "matrix/deroga refusal", not
     * "outside the visibility scope" — the separation the sibling Task
     * suites already draw.
     *
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

if (! function_exists('protectedTaskStatus')) {
    function protectedTaskStatus(TaskStatusSystemKey $key): TaskStatus
    {
        return TaskStatus::query()->where('system_key', $key->value)->firstOrFail();
    }
}

// ---------------------------------------------------------------------------
// AC-016/AC-017/AC-018 — complete, CASO 1 (no validation_status_id)
// ---------------------------------------------------------------------------

it('AC-016: an assignee completes a task with no closure feedback required (CASO 1.a)', function () {
    $actor = taskActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id)
        ->assertJsonPath('data.completion_percentage', 100)
        ->assertJsonPath('data.completion_date', now()->toDateString());

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_status_id' => protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id,
        'completion_date' => now()->toDateString(),
    ]);
});

it('AC-017: 422 on closure_feedback when the task requires it and none is submitted (CASO 1.b), and the status does not move', function () {
    $actor = taskActorWith(['complete']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->requiringClosureFeedback()->inStatus($open)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)
        ->assertJsonValidationErrors('closure_feedback');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $open->id]);
});

it('AC-018: 200 and the feedback is persisted when it is submitted', function () {
    $actor = taskActorWith(['complete']);
    $open = TaskStatus::factory()->completion(0)->create();
    $task = Task::factory()->requiringClosureFeedback()->inStatus($open)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'closure_feedback' => 'Consegnato al cliente.',
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'closure_feedback' => 'Consegnato al cliente.']);
});

// ---------------------------------------------------------------------------
// AC-019/AC-020/AC-021 — complete, CASO 2 (validation_status_id) and availability
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0121, rettifica di D-4 della spec 0116): il
// client non sceglie piu' liberamente il CASO 2 inviando validation_status_id
// — il percorso e' derivato dal server da `requires_validation` + mandato
// dell'attore (D-2). Un Task NON flaggato (il default della factory) e un
// assegnatario che invia validation_status_id ora e' 422 (spec 0121 AC-008,
// vedi TaskValidationRequirementTest.php); questo test diventa il suo
// omologo con il flag acceso, cosi' la CASO 2 resta provata sull'endpoint
// reale con lo stesso attore/ruolo di prima.
it('AC-019: a flagged Task lets an assignee complete with a validation_status_id, moving to that status without closing (spec 0121 percorso derivato)', function () {
    $actor = taskActorWith(['complete']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $validationStatus->id)
        ->assertJsonPath('data.completion_date', now()->toDateString());

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_status_id' => $validationStatus->id,
        'completion_date' => now()->toDateString(),
    ]);
});

it('AC-020: 422 on validation_status_id when it does not belong to the in_validation group', function () {
    $actor = taskActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $openStatus = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $openStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('validation_status_id');
});

it('AC-021: 422 when the task is already closed_positive', function () {
    $actor = taskActorWith(['complete']);
    $task = Task::factory()->inStatus(protectedTaskStatus(TaskStatusSystemKey::ClosedPositive))->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-022/AC-023 — uncomplete
// ---------------------------------------------------------------------------

it('AC-022: an assignee reopens a closed task onto the resume status, clearing feedback and completion date', function () {
    $actor = taskActorWith(['complete']);
    $closed = protectedTaskStatus(TaskStatusSystemKey::ClosedPositive);
    $task = Task::factory()->inStatus($closed)->create([
        'closure_feedback' => 'Motivazione precedente.',
        'completion_date' => now()->toDateString(),
    ]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/uncomplete")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::InProgress)->id)
        ->assertJsonPath('data.closure_feedback', null)
        ->assertJsonPath('data.completion_date', null);

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'task_status_id' => protectedTaskStatus(TaskStatusSystemKey::InProgress)->id,
        'closure_feedback' => null,
        'completion_date' => null,
    ]);
});

it('AC-023: an assignee reopens a task awaiting validation onto the resume status', function () {
    $actor = taskActorWith(['complete']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/uncomplete")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::InProgress)->id);
});

// ---------------------------------------------------------------------------
// AC-024/AC-025/AC-026/AC-027 — approve/reject
// ---------------------------------------------------------------------------

it('AC-024: the creator approves a task awaiting validation, closing it positively', function () {
    $actor = taskActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::ClosedPositive)->id)
        ->assertJsonPath('data.completion_percentage', 100);
});

// REQUIREMENT CHANGED (spec 0153, D-6): reject now lands on the `assigned`
// system status (was `in_progress`) and CLEARS `closure_feedback` (was kept)
// — the validator's motivation is no longer carried forward onto the
// reopened task.
it('AC-025 (spec 0153, D-6): the creator rejects a task awaiting validation onto `assigned`, clearing the feedback', function () {
    $actor = taskActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)
        ->create(['closure_feedback' => 'Manca il documento firmato.']);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::Assigned)->id)
        ->assertJsonPath('data.closure_feedback', null)
        ->assertJsonPath('data.completion_date', null);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'closure_feedback' => null]);
});

it('AC-026: a plain assignee (not creator, no manageAll) may not approve', function () {
    $actor = taskActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(403);
});

it('AC-027: 422 when approving a task still in the open phase', function () {
    $actor = taskActorWith(['validate']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-028/AC-029 — block
// ---------------------------------------------------------------------------

it('AC-028: the creator blocks an unblocked task, and a second block answers 422', function () {
    $actor = taskActorWith(['block']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")
        ->assertOk()
        ->assertJsonPath('data.is_blocked', true);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => true]);

    $this->postJson("/api/tasks/{$task->id}/block")->assertStatus(422);
});

it('AC-029: a plain assignee may not block (matrix: reserved to creator/requester/manager)', function () {
    $actor = taskActorWith(['block']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/block")->assertStatus(403);
});

// ---------------------------------------------------------------------------
// AC-030 — is_blocked freezes every action except unblock
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-7): is_blocked no longer freezes EVERY
// action — only complete/approve keep the 409. uncomplete/reject are two of
// the three actions that LIFT the block instead (D-7), so they succeed on a
// blocked task rather than being refused, split into their own cases below.
it('AC-030 (spec 0153, D-7): a blocked open task answers 409 on complete, and 200 on unblock', function () {
    $actor = taskActorWith(['complete', 'block']);
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertStatus(409);

    $this->postJson("/api/tasks/{$task->id}/unblock")
        ->assertOk()
        ->assertJsonPath('data.is_blocked', false);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => false]);
});

it('AC-030 (spec 0153, D-7): approve keeps the 409 on a blocked in-validation task', function () {
    $actor = taskActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(409);
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => true]);
});

it('AC-030 (spec 0153, D-7, NEW): uncomplete succeeds on a blocked closed task, lifting the block', function () {
    $actor = taskActorWith(['complete']);
    $closed = protectedTaskStatus(TaskStatusSystemKey::ClosedPositive);
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/uncomplete")
        ->assertOk()
        ->assertJsonPath('data.is_blocked', false);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => false]);
});

it('AC-030 (spec 0153, D-7, NEW): reject succeeds on a blocked in-validation task, lifting the block', function () {
    $actor = taskActorWith(['validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create(['is_blocked' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.is_blocked', false)
        ->assertJsonPath('data.task_status_id', protectedTaskStatus(TaskStatusSystemKey::Assigned)->id);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => false]);
});

// ---------------------------------------------------------------------------
// AC-010 — an ordinary manager who is ALSO an assignee decays (D-2)
// ---------------------------------------------------------------------------

it('AC-010: an actor with tasks.manageAll who is ALSO an assignee of that task may not approve (D-2 deroga)', function () {
    // The middle case between AC-026 (a plain assignee, no manageAll at
    // all: the deroga is never even reached) and AC-011 (a super-admin,
    // which takes the Gate::before path instead of TaskPolicy): an
    // ORDINARY actor holding `tasks.manageAll` through configuration, who
    // is also an assignee of THIS task, must decay to plain assignee.
    $actor = taskActorWith(['manageAll', 'validate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(403);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
});

// ---------------------------------------------------------------------------
// AC-011 — the D-2 deroga is re-asserted in the Service, past Gate::before
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0126, D-1): the super-admin no longer decays as
// an assignee (see TaskSuperAdminAssigneeTest AC-001, where the same
// scenario now expects 200). The deroga this test proves — a manager who is
// ALSO an assignee may not approve, re-asserted in the Service past
// Gate::before — stays true for an ORDINARY `tasks.manageAll` actor, so the
// actor here is rewritten as one instead of a super-admin.
it('AC-011: a manager (tasks.manageAll, not super-admin) who is also an assignee may not approve (the Service re-asserts D-2 past Gate::before)', function () {
    $actor = taskActorWith(['manageAll', 'validate']);

    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->inStatus($inValidation)->create();
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(403);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $inValidation->id]);
});

// ---------------------------------------------------------------------------
// AC-040 — ability and matrix are ANDed, not ORed, exercised over HTTP
// ---------------------------------------------------------------------------

it('AC-040: the creator of a task WITHOUT tasks.complete gets 403 from POST /complete (ability AND matrix, not OR)', function () {
    // TaskRecordRoleMatrixTest already covers this at the Gate level, from
    // before the endpoint existed; now that it does, the same refusal must
    // hold over HTTP too. Deliberately NOT given `complete`: the matrix
    // would allow it (the actor is the creator), the ability would not.
    $actor = taskActorWith([]);
    $task = Task::factory()->forCreator($actor)->create();
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertStatus(403);

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
});
