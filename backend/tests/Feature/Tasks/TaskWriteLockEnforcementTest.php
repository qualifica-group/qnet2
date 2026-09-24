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
| The structural write lock (spec 0116, D-6/D-7, AC-031..AC-035)
|--------------------------------------------------------------------------
|
| App\Services\Tasks\TaskWriteLock freezes a Task's STRUCTURAL fields —
| never its two OPERATIVE ones (`task_status_id`, `closure_feedback`) —
| when `is_blocked` is true or the current status phase is `in_validation`,
| `closed_positive` or `closed_negative`. DELETE gets no operative
| exception at all. `is_blocked` itself is `prohibited` at the FormRequest
| layer unconditionally (D-6), independent of the lock: this suite covers
| both. The role-on-record matrix (D-1) belongs to TaskPolicyTest; every
| actor here already owns the mandate (creator) so a 422/409 always means
| the write lock, never the record-role matrix.
*/

if (! function_exists('taskActorWith')) {
    /**
     * An actor holding $abilities on `tasks`. `viewAll` is granted on top by
     * default so a 403 in a suite that is NOT about the membership scoping
     * always means "missing resource permission" — the separation
     * WorkOrderSecurityTest/WorkOrderVisibilityTest already draw.
     *
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
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

// ---------------------------------------------------------------------------
// AC-031 — a blocked Task accepts the operative keys, refuses the rest
// ---------------------------------------------------------------------------

it('AC-031: on a blocked task, PATCH sending a structural key (title) is 422 and nothing changes', function () {
    $actor = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true, 'title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertStatus(422)->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Originale', 'is_blocked' => true]);
});

// REQUIREMENT CHANGED (spec 0126, D-4c/D-6): `task_status_id` stays an
// OPERATIVE key for TaskWriteLock (the structural lock itself still lets it
// ride through a frozen Task), but `TaskManualStatusGuard` now adds its OWN
// veto on top: a manual status change is refused on a blocked Task
// regardless of phase, so the "200 on a blocked task" outcome the old spec
// 0116 D-7 produced no longer holds.
it('AC-031 (spec 0126, D-4c): on a blocked task, PATCH sending task_status_id is 422 with the blocked message', function () {
    $actor = taskActorWith(['update', 'view']);
    $target = TaskStatus::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.task_status_id.0', 'A blocked task cannot change status.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId, 'is_blocked' => true]);
});

// ---------------------------------------------------------------------------
// AC-032 — a violation rolls back the WHOLE transaction, not just the
// offending key
// ---------------------------------------------------------------------------

it('AC-032: PATCH mixing a structural key with an operative one on a frozen task is 422 on the structural key, and the operative one is not written either', function () {
    $actor = taskActorWith(['update', 'view']);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create([
        'title' => 'Originale',
        'closure_feedback' => 'Vecchio feedback',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'title' => 'Nuovo titolo',
        'closure_feedback' => 'Nuovo feedback',
    ])->assertStatus(422)->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Originale',
        'closure_feedback' => 'Vecchio feedback',
    ]);
});

// ---------------------------------------------------------------------------
// AC-050 — the operative task_status_id key does not open the lock for a
// structural key riding along, even when it targets an UNFROZEN status: the
// lock reads WHERE THE TASK WAS (before this PATCH), not where the same
// PATCH would send it — closing exactly the gap the pre-fill evaluation
// order was chosen to prevent (see TaskService::update() docblock).
// ---------------------------------------------------------------------------

it('AC-050: blocked via is_blocked — a structural key is refused even when task_status_id targets an unfrozen status, and nothing is written', function () {
    $actor = taskActorWith(['update', 'view']);
    $openTarget = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true, 'title' => 'Originale']);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'task_status_id' => $openTarget->id,
        'title' => 'Nuovo titolo',
    ])->assertStatus(422)->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Originale',
        'task_status_id' => $originalStatusId,
        'is_blocked' => true,
    ]);
});

it('AC-050: blocked via phase — a structural key is refused even when task_status_id targets an unfrozen status, and nothing is written', function () {
    $actor = taskActorWith(['update', 'view']);
    $closed = TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create();
    $openTarget = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", [
        'task_status_id' => $openTarget->id,
        'title' => 'Nuovo titolo',
    ])->assertStatus(422)->assertJsonValidationErrors('title');

    $this->assertDatabaseHas('tasks', [
        'id' => $task->id,
        'title' => 'Originale',
        'task_status_id' => $closed->id,
    ]);
});

// ---------------------------------------------------------------------------
// AC-033 — DELETE on the task's OWN frozen state
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-5): canDelete() now folds $task's own
// phase/blocked state into the RECORD-ROLE check itself, so a Task in
// in_validation is refused 403 (past TaskPolicy/canDelete), never reaching
// TaskWriteLock::assertDeletable()'s 409 — that 409 now fires ONLY for the
// ancestor-lock cascade (see TaskWriteLockCascadeTest AC-032, unaffected).
it('AC-033 (spec 0153, D-5): DELETE on a task in the in_validation phase is 403, task kept', function () {
    $actor = taskActorWith(['delete', 'view']);
    $status = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($status)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertStatus(403);

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

// ---------------------------------------------------------------------------
// AC-034 — the lock does not apply outside a frozen phase
// ---------------------------------------------------------------------------

it('AC-034: on an open, unblocked task, PATCH sending title is 200 (the lock does not apply)', function () {
    $actor = taskActorWith(['update', 'view']);
    $open = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    $task = Task::factory()->forCreator($actor)->inStatus($open)->create(['title' => 'Originale']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Nuovo titolo'])
        ->assertOk()->assertJsonPath('data.title', 'Nuovo titolo');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Nuovo titolo']);
});

// ---------------------------------------------------------------------------
// AC-035 — is_blocked is prohibited from a PATCH, unconditionally (D-6)
// ---------------------------------------------------------------------------

it('AC-035: PATCH sending is_blocked is 422 for the creator, on an unfrozen task', function () {
    $actor = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => false]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['is_blocked' => true])
        ->assertStatus(422)->assertJsonValidationErrors('is_blocked');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => false]);
});

it('AC-035: PATCH sending is_blocked is 422 even for a super-admin', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));
    $task = Task::factory()->create(['is_blocked' => false]);
    Sanctum::actingAs($superAdmin);

    $this->patchJson("/api/tasks/{$task->id}", ['is_blocked' => true])
        ->assertStatus(422)->assertJsonValidationErrors('is_blocked');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'is_blocked' => false]);
});
