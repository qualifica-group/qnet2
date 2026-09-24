<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskUpdateRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| POST /api/tasks/{task}/request-update (spec 0153, D-14, superseding spec
| 0118 D-10..D-14 and spec 0126 D-6)
|--------------------------------------------------------------------------
|
| The notification class itself (channels, deep link, default body) is
| TaskUpdateRequestedNotificationTest's territory; this suite exercises the
| endpoint: the matrix row (a pure watcher is now REFUSED, D-14 drops the
| observer), availability (closed/in_validation/BLOCKED all 422 now), the
| `target` groups and their CC (AC-017), and the GET-detail flag/response
| shape.
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

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function requestUpdatePayload(array $overrides = []): array
{
    return [
        'target' => 'assignees',
        'message' => 'Serve un aggiornamento entro venerdi.',
        ...$overrides,
    ];
}

// ---------------------------------------------------------------------------
// The matrix row, over HTTP
// ---------------------------------------------------------------------------

it('the creator gets 200', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())->assertOk();
});

it('the requester gets 200', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create(['requester_id' => $actor->id]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())->assertOk();
});

// REQUIREMENT CHANGED (spec 0153, D-14): "ammessa a richiedente, creatore o
// gestore (non all'osservatore)" — a pure watcher is now REFUSED, the
// opposite of spec 0118's own D-10. (TaskAbilityResolver::canRequestUpdate()
// drops the watcher case — a change owned alongside this test.)
it('REQUIREMENT CHANGED (D-14): a pure watcher now gets 403, the observer is no longer admitted', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);
});

it('a pure assignee gets 403', function () {
    $actor = taskActorWith(['requestUpdate']);
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);
});

it('a manager (tasks.manageAll) who is NOT an assignee gets 200', function () {
    $actor = taskActorWith(['requestUpdate', 'manageAll']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())->assertOk();
});

it('a manager who is ALSO an assignee of this task gets 403 (D-2 deroga of spec 0116)', function () {
    $actor = taskActorWith(['requestUpdate', 'manageAll']);
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);
});

it('the creator WITHOUT tasks.requestUpdate gets 403', function () {
    $actor = taskActorWith([]);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);
});

it('an actor outside the visibility scope gets 403', function () {
    $actor = taskActorWith(['requestUpdate'], withViewAll: false);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// Availability
// ---------------------------------------------------------------------------

// REQUIREMENT CHANGED (spec 0153, D-14 REVERSES spec 0126, D-6): a blocked
// task now 422s — "Richiedi aggiornamento" no longer admits a blocked task.
it('REQUIREMENT CHANGED (D-14): a blocked task now answers 422, no notification sent', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(422);

    Notification::assertNothingSent();
});

it('a closed_positive task answers 422', function () {
    $actor = taskActorWith(['requestUpdate']);
    $closed = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(422);
});

it('a task in_validation answers 422', function () {
    $actor = taskActorWith(['requestUpdate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(422);
});

it('a task in the pending phase answers 200 — isCompletable includes pending', function () {
    $actor = taskActorWith(['requestUpdate']);
    $pending = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($pending)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())->assertOk();
});

// ---------------------------------------------------------------------------
// target / message (D-14)
// ---------------------------------------------------------------------------

it('an invalid target answers 422 on target', function () {
    $actor = taskActorWith(['requestUpdate']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'everyone']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');
});

it('a target with zero recipients answers 422 on target, zero notifications sent', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'observers']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('target');

    Notification::assertNothingSent();
});

it('a missing message answers 422 on message', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['target' => 'assignees'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

it('a message shorter than 3 characters answers 422 on message', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['message' => 'hi']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

it('a message over 2000 characters answers 422 on message', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['message' => str_repeat('a', 2001)]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

// ---------------------------------------------------------------------------
// AC-017 — target groups and CC
// ---------------------------------------------------------------------------

it('AC-017: target=assignees notifies every assignee and CCs the watchers who are not assignees', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $assigneeWatcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach([$assignee->id, $assigneeWatcher->id]);
    $task->watchers()->attach([$watcher->id, $assigneeWatcher->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'assignees']))
        ->assertOk();

    Notification::assertSentTo(
        [$assignee, $assigneeWatcher],
        TaskUpdateRequested::class,
        fn (TaskUpdateRequested $notification, array $channels, User $notifiable): bool => $notification->toArray($notifiable)['is_cc'] === false,
    );
    Notification::assertSentTo(
        $watcher,
        TaskUpdateRequested::class,
        fn (TaskUpdateRequested $notification, array $channels, User $notifiable): bool => $notification->toArray($notifiable)['is_cc'] === true,
    );
    Notification::assertSentTimes(TaskUpdateRequested::class, 3);
});

it('target=observers notifies every watcher and sends no copy', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $watcher = User::factory()->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->watchers()->attach($watcher->id);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'observers']))
        ->assertOk();

    Notification::assertSentTo($watcher, TaskUpdateRequested::class);
    Notification::assertNotSentTo($assignee, TaskUpdateRequested::class);
    Notification::assertSentTimes(TaskUpdateRequested::class, 1);
});

it('target=all notifies the union of assignees and watchers, deduplicated, with no copy', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $both = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach([$assignee->id, $both->id]);
    $task->watchers()->attach([$watcher->id, $both->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'all']))
        ->assertOk();

    Notification::assertSentTo([$assignee, $watcher, $both], TaskUpdateRequested::class);
    Notification::assertSentTimes(TaskUpdateRequested::class, 3);
});

// D-14: "L'attore non e' escluso" — unlike every other Task notification.
it('D-14: the actor is NOT excluded when they are also a recipient of the chosen target', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate', 'manageAll']);
    $task = Task::factory()->create();
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload(['target' => 'observers']))
        ->assertOk();

    Notification::assertSentTo($actor, TaskUpdateRequested::class);
});

// ---------------------------------------------------------------------------
// zero notifications on refusal
// ---------------------------------------------------------------------------

it('a 403 refusal sends zero notifications', function () {
    Notification::fake();
    $actor = taskActorWith([]);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())
        ->assertStatus(403);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// the GET flag and the response shape
// ---------------------------------------------------------------------------

it('permissions.actions.request_update is true for the creator and false for a pure assignee', function () {
    $creatorActor = taskActorWith(['view', 'requestUpdate']);
    $assigneeActor = taskActorWith(['view', 'requestUpdate']);
    $task = Task::factory()->forCreator($creatorActor)->create();
    $task->assignees()->attach($assigneeActor->id);

    Sanctum::actingAs($creatorActor);
    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.request_update', true);

    Sanctum::actingAs($assigneeActor);
    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.request_update', false);
});

it('a successful request-update response has the same shape as GET /api/tasks/{id}', function () {
    $actor = taskActorWith(['view', 'requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $getResponse = $this->getJson("/api/tasks/{$task->id}")->assertOk();
    $actionResponse = $this->postJson("/api/tasks/{$task->id}/request-update", requestUpdatePayload())->assertOk();

    expect(array_keys($actionResponse->json()))->toBe(array_keys($getResponse->json()))
        ->and(array_keys($actionResponse->json('data')))->toBe(array_keys($getResponse->json('data')));

    // D-14: the Task itself is untouched by this action.
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);
});
