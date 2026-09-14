<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Notifications\TaskClosed;
use App\Notifications\TaskValidationRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `requires_validation` and the completion percorso derivato dal server
| (spec 0121, D-2/D-3/D-4/D-5, AC-004..AC-014)
|--------------------------------------------------------------------------
|
| The client never chooses SE a completion goes into validation any more:
| `TaskAbilityResolver::completionRequiresValidation()` derives it from the
| flag and the actor's mandate over the record. This suite exercises that
| single rule end-to-end, over the real /complete and PATCH endpoints, plus
| the PATCH bypass TaskValidationRequirementGuard closes (D-5).
*/

if (! function_exists('taskValidationActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function taskValidationActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['view', 'update', 'complete', 'manageAll', 'viewAll'] as $ability) {
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

if (! function_exists('inValidationStatus')) {
    function inValidationStatus(): TaskStatus
    {
        return TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    }
}

if (! function_exists('validTimeEntryPayload')) {
    /**
     * A valid `time_entry` (spec 0123, D-1: mandatory on every /complete
     * call, regardless of what THIS suite is exercising).
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

// ---------------------------------------------------------------------------
// AC-004/AC-005 — the assignee percorso: validation required, status enforced
// ---------------------------------------------------------------------------

it('AC-004: a flagged Task sends a plain assignee into validation, does not close, and notifies TaskValidationRequested only', function () {
    Notification::fake();
    $actor = taskValidationActorWith(['complete']);
    $requester = User::factory()->create();
    $task = Task::factory()->requiringValidation()->create(['requester_id' => $requester->id]);
    $task->assignees()->attach($actor->id);
    $validationStatus = inValidationStatus();
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
    Notification::assertSentTo($requester, TaskValidationRequested::class);
    Notification::assertNothingSentTo($actor);
    Notification::assertSentTimes(TaskClosed::class, 0);
});

it('AC-005: the same assignee without validation_status_id gets 422, and the Task is untouched', function () {
    $actor = taskValidationActorWith(['complete']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $originalStatusId = $task->task_status_id;
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)->assertJsonValidationErrors('validation_status_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
});

// ---------------------------------------------------------------------------
// AC-006/AC-007 — who owns the mandate always closes directly
// ---------------------------------------------------------------------------

it('AC-006: the creator, the requester or a manager (not assignee) close a flagged Task directly, no validation_status_id needed', function (string $role) {
    $actor = taskValidationActorWith(['complete', 'manageAll']);
    $task = match ($role) {
        'creator' => Task::factory()->requiringValidation()->forCreator($actor)->create(),
        'requester' => Task::factory()->requiringValidation()->create(['requester_id' => $actor->id]),
        'manager' => Task::factory()->requiringValidation()->create(),
    };
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->value('id'));
})->with(['creator', 'requester', 'manager']);

it('AC-007: a manageAll holder who is ALSO an assignee of the flagged Task needs validation like any assignee', function () {
    $actor = taskValidationActorWith(['complete', 'manageAll']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = inValidationStatus();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)->assertJsonValidationErrors('validation_status_id');

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $validationStatus->id);
});

// ---------------------------------------------------------------------------
// AC-008 — an unflagged Task keeps the retired free-choice CASO rejected
// ---------------------------------------------------------------------------

it('AC-008: on an unflagged Task, an assignee submitting validation_status_id gets 422; without it, closes directly', function () {
    $actor = taskValidationActorWith(['complete']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = inValidationStatus();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertStatus(422)->assertJsonValidationErrors('validation_status_id');
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->value('id'));
});

// ---------------------------------------------------------------------------
// AC-009/AC-010/AC-011 — the feedback rule is independent of the percorso (D-4)
// ---------------------------------------------------------------------------

it('AC-009: both flags on, an assignee completing into validation without feedback gets 422 on closure_feedback; with it, 200', function () {
    $actor = taskValidationActorWith(['complete']);
    $task = Task::factory()->requiringValidation()->requiringClosureFeedback()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = inValidationStatus();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'closure_feedback' => 'In attesa di verifica.',
        'time_entry' => validTimeEntryPayload(),
    ])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'closure_feedback' => 'In attesa di verifica.']);
});

it('AC-010: requires_closure_feedback off, requires_validation on: an assignee completes into validation with no feedback -> 200', function () {
    $actor = taskValidationActorWith(['complete']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $validationStatus = inValidationStatus();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk();
});

it('AC-011: requires_closure_feedback on, requires_validation off: the creator closing without feedback still gets 422 (preserved)', function () {
    $actor = taskValidationActorWith(['complete']);
    $task = Task::factory()->requiringClosureFeedback()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertStatus(422)->assertJsonValidationErrors('closure_feedback');
});

// ---------------------------------------------------------------------------
// AC-012/AC-013 — the PATCH bypass is closed (D-5)
// ---------------------------------------------------------------------------

dataset('closingSystemStatuses', [
    'closed_positive' => [TaskStatusSystemKey::ClosedPositive],
    'closed_negative' => [TaskStatusSystemKey::ClosedNegative],
]);

it('AC-012: a flagged Task refuses a PATCH straight to a closing status from a plain assignee', function (TaskStatusSystemKey $key) {
    $actor = taskValidationActorWith(['update']);
    $task = Task::factory()->requiringValidation()->create();
    $task->assignees()->attach($actor->id);
    $originalStatusId = $task->task_status_id;
    $closing = TaskStatus::query()->where('system_key', $key->value)->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['task_status_id' => $closing->id])
        ->assertStatus(422)->assertJsonValidationErrors('task_status_id');

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $originalStatusId]);
})->with('closingSystemStatuses');

// REQUIREMENT CHANGED (spec 0123, D-4): this test used `closed_positive` as
// the reachable closing status. D-4 now reserves that phase to the domain
// actions for EVERY actor, mandate or not, which would make both PATCHes
// below 422 regardless of the very mandate this test means to pin.
// Retargeted to `closed_negative`, the one closing status D-4 leaves
// selectable, so the assertions still exercise the D-5 bypass rule
// unchanged (see TaskActionOnlyStatusTest.php, AC-011, for the retired
// closed_positive case, now unconditional on the actor).
it('AC-013: the creator may PATCH the same flagged Task to a closing status; an assignee may on an unflagged one', function () {
    $creator = taskValidationActorWith(['update']);
    $flagged = Task::factory()->requiringValidation()->forCreator($creator)->create();
    $closing = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedNegative->value)->firstOrFail();
    Sanctum::actingAs($creator);

    $this->patchJson("/api/tasks/{$flagged->id}", ['task_status_id' => $closing->id])->assertOk();

    $assignee = taskValidationActorWith(['update']);
    $unflagged = Task::factory()->create();
    $unflagged->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->patchJson("/api/tasks/{$unflagged->id}", ['task_status_id' => $closing->id])->assertOk();
});

// ---------------------------------------------------------------------------
// AC-014 — permissions.actions.complete_to_validation
// ---------------------------------------------------------------------------

it('AC-014: complete_to_validation is true only for a non-mandate assignee on a flagged, completable Task', function () {
    $assignee = taskValidationActorWith(['view', 'complete', 'update']);
    $flagged = Task::factory()->requiringValidation()->create();
    $flagged->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->getJson("/api/tasks/{$flagged->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete_to_validation', true);

    $creator = taskValidationActorWith(['view', 'complete', 'update']);
    $creatorTask = Task::factory()->requiringValidation()->forCreator($creator)->create();
    Sanctum::actingAs($creator);

    $this->getJson("/api/tasks/{$creatorTask->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete_to_validation', false);

    $unflagged = Task::factory()->create();
    $unflagged->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->getJson("/api/tasks/{$unflagged->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete_to_validation', false);

    $closed = Task::factory()->requiringValidation()
        ->inStatus(TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail())
        ->create();
    $closed->assignees()->attach($assignee->id);
    Sanctum::actingAs($assignee);

    $this->getJson("/api/tasks/{$closed->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.complete', false)
        ->assertJsonPath('permissions.actions.complete_to_validation', false);
});
