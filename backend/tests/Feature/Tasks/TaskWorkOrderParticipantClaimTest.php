<?php

use App\Enums\TaskStatusGroup;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| Spec 0387 — a commessa participant claims an unassigned task by closing it
|--------------------------------------------------------------------------
|
| A Partecipante of the commessa may complete one of its Tasks with no
| assignee: the completion makes them its assignee and the rest of the
| percorso is the assignee's one.
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

it('AC-003: no claim on an assigned task, for a supervisor only, or on a task outside the commessa', function () {
    $actor = participantClaimActor();
    $workOrder = participantClaimWorkOrder($actor);
    $assigned = participantClaimTask($workOrder);
    $assigned->assignees()->attach(User::factory()->create()->id);

    $supervisedWorkOrder = WorkOrder::factory()->create();
    $supervisedWorkOrder->supervisors()->attach($actor->id);
    $supervised = participantClaimTask($supervisedWorkOrder);
    Sanctum::actingAs($actor);

    foreach ([$assigned, $supervised] as $task) {
        $this->getJson("/api/tasks/{$task->id}")->assertJsonPath('permissions.actions.complete', false);
        $this->postJson("/api/tasks/{$task->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertForbidden();
    }

    $foreign = participantClaimTask(WorkOrder::factory()->create());
    $this->postJson("/api/tasks/{$foreign->id}/complete", ['time_entry' => validTimeEntryPayload()])->assertForbidden();
});

it('AC-004: before completing the participant cannot edit the task, and a failed guard leaves no assignment', function () {
    $actor = participantClaimActor();
    $task = participantClaimTask(participantClaimWorkOrder($actor));
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['description' => 'x'])->assertForbidden();
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
