<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Manual status change (spec 0126, D-4, D-6, AC-008..AC-011)
|--------------------------------------------------------------------------
|
| App\Services\Tasks\TaskManualStatusGuard: (b) the Task's CURRENT phase must
| be `open`/`pending` for a PATCH `task_status_id` to be judged at all, (c) a
| blocked Task admits none, regardless of phase. Both are evaluated on the
| PRE-fill state, ahead of TaskActionOnlyStatusGuard's own target-group check
| (D-4a, spec 0123, untouched). D-6 also lifts the block on "Richiedi
| aggiornamento" while leaving complete/uncomplete/approve/reject at 409.
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

// ---------------------------------------------------------------------------
// AC-008 — from `open`, pending and closed_negative are reachable; the
// existing reserved-target 422 (D-4a, spec 0123) is untouched
// ---------------------------------------------------------------------------

it('AC-008: an open task PATCHes to pending: 200', function () {
    $creator = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($creator)->create();
    $target = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertOk()->assertJsonPath('data.task_status_id', $target->id);
});

it('AC-008: an open task PATCHes to closed_negative without a required feedback: 200', function () {
    $creator = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($creator)->create();
    $target = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertOk()->assertJsonPath('data.task_status_id', $target->id);
});

it('AC-008: an open task PATCHes toward in_validation or closed_positive: 422 (invariato, D-4a)', function (TaskStatusGroup $group) {
    $creator = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($creator)->create();
    $target = TaskStatus::factory()->group($group)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $target->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');
})->with([
    'in_validation' => [TaskStatusGroup::InValidation],
    'closed_positive' => [TaskStatusGroup::ClosedPositive],
]);

// ---------------------------------------------------------------------------
// AC-009 — from in_validation or closed_negative, a manual PATCH toward
// `open` is refused with the D-4b message; change_status is false
// ---------------------------------------------------------------------------

it('AC-009: a task in in_validation PATCHing task_status_id toward open is 422 (b), status invariato, change_status false', function () {
    $creator = taskActorWith(['update', 'view']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $task = Task::factory()->forCreator($creator)->inStatus($inValidation)->create();
    $openTarget = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $openTarget->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.task_status_id.0', 'The status of this task can only be changed through its actions.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $inValidation->id]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.change_status', false);
});

it('AC-009: a task in closed_negative PATCHing task_status_id toward open is 422 (b), status invariato, change_status false', function () {
    $creator = taskActorWith(['update', 'view']);
    $closedNegative = TaskStatus::factory()->group(TaskStatusGroup::ClosedNegative)->create();
    $task = Task::factory()->forCreator($creator)->inStatus($closedNegative)->create();
    $openTarget = TaskStatus::factory()->group(TaskStatusGroup::Open)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $openTarget->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.task_status_id.0', 'The status of this task can only be changed through its actions.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $closedNegative->id]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()->assertJsonPath('permissions.actions.change_status', false);
});

// ---------------------------------------------------------------------------
// AC-010 — a blocked task in an open/pending phase refuses the manual change
// with the D-4c message; closure_feedback alone still writes
// ---------------------------------------------------------------------------

it('AC-010: a blocked task in open PATCHing task_status_id toward pending is 422 (c); change_status/close_via_status false', function () {
    $creator = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($creator)->create(['is_blocked' => true]);
    $originalStatusId = $task->task_status_id;
    $pendingTarget = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $pendingTarget->id])
        ->assertStatus(422)
        ->assertJsonPath('errors.task_status_id.0', 'A blocked task cannot change status.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId, 'is_blocked' => true]);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.change_status', false)
        ->assertJsonPath('permissions.actions.close_via_status', false);
});

it('AC-010: a blocked task PATCHing only closure_feedback is still 200', function () {
    $creator = taskActorWith(['update', 'view']);
    $task = Task::factory()->forCreator($creator)->create(['is_blocked' => true]);
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$task->id}", ['closure_feedback' => 'Nota di avanzamento.'])
        ->assertOk()->assertJsonPath('data.closure_feedback', 'Nota di avanzamento.');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'closure_feedback' => 'Nota di avanzamento.', 'is_blocked' => true]);
});

// ---------------------------------------------------------------------------
// AC-011 — a blocked task still allows "Richiedi aggiornamento"; complete/
// uncomplete/approve/reject stay 409 (invariato, spec 0116 D-8)
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-14, "prevale su spec 0126 D-6"): a
// blocked task no longer admits "Richiedi aggiornamento" — the exemption
// this test used to pin is reversed, and the payload itself moved from
// `recipient_ids` to the fixed `target`/`message` shape.
it('AC-011 (spec 0153, D-14): request-update on a blocked task now answers 422, no notification sent', function () {
    Notification::fake();
    $creator = taskActorWith(['update', 'view', 'requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($creator)->create(['is_blocked' => true]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($creator);

    $this->postJson("/api/tasks/{$task->id}/request-update", [
        'target' => 'assignees',
        'message' => 'Serve un aggiornamento.',
    ])->assertStatus(422);

    Notification::assertNothingSent();
});

it('AC-011: complete, uncomplete, approve and reject stay 409 on a blocked task (invariato)', function () {
    $creator = taskActorWith(['update', 'view', 'complete', 'validate']);
    $task = Task::factory()->forCreator($creator)->create(['is_blocked' => true]);
    Sanctum::actingAs($creator);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertStatus(409);
    $this->postJson("/api/tasks/{$task->id}/approve")->assertStatus(409);
    // REQUIREMENT CHANGED (spec 0153, D-7): uncomplete/reject no longer veto a
    // blocked task (they lift the block); on this OPEN task they fail on the
    // phase check instead.
    $this->postJson("/api/tasks/{$task->id}/uncomplete")->assertStatus(422);
    $this->postJson("/api/tasks/{$task->id}/reject")->assertStatus(422);
});
