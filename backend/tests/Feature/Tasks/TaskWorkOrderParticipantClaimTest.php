<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\TaskClosed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0387 — a commessa member claims a task of the commessa by closing it
|--------------------------------------------------------------------------
|
| A Responsabile or Partecipante of the commessa may complete one of its Tasks,
| with or without assignees (rev.2): the completion makes them one of its
| assignees and the rest of the percorso is the assignee's one.
*/

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $taskAbilities
 */
function participantClaimActor(array $taskAbilities = ['viewAny', 'view', 'update', 'complete']): User
{
    $user = taskActorWith($taskAbilities, withViewAll: false);
    Permission::findOrCreate('work-orders.view');
    $user->givePermissionTo('work-orders.view');

    return $user;
}

/**
 * @param  array<string, mixed>  $attributes
 */
function participantClaimTask(WorkOrder $workOrder, array $attributes = []): Task
{
    $creator = User::factory()->create();

    return Task::factory()->forCreator($creator)->create([
        'requester_id' => $creator->id,
        'work_order_id' => $workOrder->id,
        ...$attributes,
    ]);
}

function participantClaimWorkOrder(User $participant): WorkOrder
{
    $workOrder = WorkOrder::factory()->create();
    $workOrder->participants()->attach($participant->id, ['position' => 1]);

    return $workOrder;
}

it('AC-001: the participant completes an unassigned task of the commessa and becomes its assignee', function () {
    $actor = participantClaimActor();
    $task = participantClaimTask(participantClaimWorkOrder($actor));
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")
        ->assertJsonPath('permissions.actions.complete', true)
        ->assertJsonPath('permissions.actions.complete_to_validation', false);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.assignees.0.id', $actor->id);

    expect($task->fresh()->completion_date)->not->toBeNull()
        ->and($task->timeEntries()->where('user_id', $actor->id)->count())->toBe(1);
});

it('AC-002: on a task requiring validation the participant becomes assignee and sends it into validation', function () {
    $actor = participantClaimActor();
    $task = participantClaimTask(participantClaimWorkOrder($actor), ['requires_validation' => true]);
    $validationStatus = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('permissions.actions.complete_to_validation', true);

    $this->postJson("/api/tasks/{$task->id}/complete", [
        'validation_status_id' => $validationStatus->id,
        'time_entry' => validTimeEntryPayload(),
    ])
        ->assertOk()
        ->assertJsonPath('data.task_status_id', $validationStatus->id)
        ->assertJsonPath('data.assignees.0.id', $actor->id);
});

// REQUIREMENT CHANGED (user decision 2026-11-17, rev.2): an assigned task of the commessa is no longer excluded.
it('AC-003: no claim on a task outside the commessa or by an actor who is not a member', function () {
    $actor = participantClaimActor();
    Sanctum::actingAs($actor);

    $foreign = participantClaimTask(WorkOrder::factory()->create());
    $this->postJson("/api/tasks/{$foreign->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertForbidden();

    $standalone = participantClaimTask(participantClaimWorkOrder(User::factory()->create()), ['work_order_id' => null]);
    $this->postJson("/api/tasks/{$standalone->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertForbidden();
});

it('AC-007: the participant closes a task already assigned to someone else in their own name, the assignee keeps the task and is told', function () {
    Notification::fake();
    $actor = participantClaimActor();
    $assignee = User::factory()->create();
    $task = participantClaimTask(participantClaimWorkOrder($actor));
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('permissions.actions.complete', true);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload(), 'for_all_assignees' => false])
        ->assertOk();

    expect($task->fresh()->completion_date)->not->toBeNull()
        ->and($task->assignees()->pluck('users.id')->sort()->values()->all())->toBe(collect([$actor->id, $assignee->id])->sort()->values()->all())
        ->and($task->timeEntries()->where('user_id', $actor->id)->count())->toBe(1)
        ->and($task->timeEntries()->where('user_id', $assignee->id)->count())->toBe(0);
    Notification::assertSentTo($assignee, TaskClosed::class);
});

// REQUIREMENT CHANGED (user directive 2026-11-23): the participant manages the commessa's tasks, so
// editing no longer waits for the claim; editing assigns nobody, and a failed completion neither.
it('AC-004: the participant edits the task without becoming its assignee, and a failed guard leaves no assignment', function () {
    $actor = participantClaimActor();
    $task = participantClaimTask(participantClaimWorkOrder($actor));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['description' => 'x'])->assertOk();
    $this->postJson("/api/tasks/{$task->id}/complete", [])->assertUnprocessable();

    expect($task->assignees()->count())->toBe(0)
        ->and($task->fresh()->completion_date)->toBeNull();
});

it('AC-005: a manageAll participant closes the task without becoming its assignee', function () {
    $actor = participantClaimActor(['viewAny', 'view', 'update', 'complete', 'manageAll']);
    $task = participantClaimTask(participantClaimWorkOrder($actor), ['requires_validation' => true]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonCount(0, 'data.assignees');

    expect($task->fresh()->completion_date)->not->toBeNull();
});

it('AC-006: the supervisor of the commessa claims an unassigned task like a participant', function () {
    $actor = participantClaimActor();
    $workOrder = WorkOrder::factory()->create();
    $workOrder->supervisors()->attach($actor->id);
    $task = participantClaimTask($workOrder);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('permissions.actions.complete', true);

    $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])
        ->assertOk()
        ->assertJsonPath('data.assignees.0.id', $actor->id);
});
