<?php

use App\Enums\TaskStatusGroup;
use App\Enums\TaskStatusSystemKey;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notifications\TaskUpdateRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// Same defense the sibling suites already draw (TaskActionsTest.php,
// TaskRecordRoleMatrixTest.php): `taskActorWith()` is declared once per
// process and PHP keeps whichever tests/Feature/Tasks/*.php copy loads
// FIRST. Creating the permission directly here is idempotent and makes this
// file correct regardless of load order.
beforeEach(function () {
    Permission::findOrCreate('tasks.requestUpdate');
});

/*
|--------------------------------------------------------------------------
| POST /api/tasks/{task}/request-update (spec 0118, D-10..D-14,
| AC-036..AC-054, AC-058..AC-060)
|--------------------------------------------------------------------------
|
| The seventh domain action, served by TaskActionService::requestUpdate()
| behind TaskPolicy::requestUpdate(). The notification class itself
| (channels, deep link, default body) is TaskUpdateRequestedNotificationTest's
| territory (AC-053, AC-055..AC-057); this suite exercises the endpoint: the
| matrix row (AC-037..AC-044), availability (AC-045..AC-048), the recipient
| membership rule (AC-049..AC-052, AC-054), the "zero notifications on
| refusal" guarantee (AC-058) and the GET-detail flag/response shape
| (AC-059/AC-060).
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
// AC-036 — the catalogue
// ---------------------------------------------------------------------------

it('AC-036: permissions:sync creates tasks.requestUpdate, and tasks.* is 15', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::query()->where('name', 'tasks.requestUpdate')->exists())->toBeTrue()
        ->and(Permission::query()->where('name', 'like', 'tasks.%')->count())->toBe(15);
});

// ---------------------------------------------------------------------------
// AC-037..AC-044 — the matrix row, over HTTP
// ---------------------------------------------------------------------------

it('AC-037: the creator gets 200', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();
});

it('AC-038: the requester gets 200', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create(['requester_id' => $actor->id]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();
});

it('AC-039: a pure watcher gets 200 — the one action a watcher may perform', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();
});

it('AC-040: a pure assignee gets 403', function () {
    $actor = taskActorWith(['requestUpdate']);
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$otherAssignee->id]])
        ->assertStatus(403);
});

it('AC-041: a manager (tasks.manageAll) who is NOT an assignee gets 200', function () {
    $actor = taskActorWith(['requestUpdate', 'manageAll']);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();
});

it('AC-042: a manager who is ALSO an assignee of this task gets 403 (D-2 deroga of spec 0116)', function () {
    $actor = taskActorWith(['requestUpdate', 'manageAll']);
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$otherAssignee->id]])
        ->assertStatus(403);
});

it('AC-042 (super-admin): the deroga is re-asserted in the Service past Gate::before', function () {
    Role::findOrCreate('super-admin');
    $actor = User::factory()->create();
    $actor->assignRole('super-admin');
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach([$actor->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$otherAssignee->id]])
        ->assertStatus(403);
});

it('AC-043: the creator WITHOUT tasks.requestUpdate gets 403', function () {
    $actor = taskActorWith([]);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(403);
});

it('AC-044: an actor outside the visibility scope gets 403', function () {
    $actor = taskActorWith(['requestUpdate'], withViewAll: false);
    $assignee = User::factory()->create();
    $task = Task::factory()->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(403);
});

// ---------------------------------------------------------------------------
// AC-045..AC-048 — availability
// ---------------------------------------------------------------------------

it('AC-045: a blocked task answers 409 and sends zero notifications', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create(['is_blocked' => true]);
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(409);

    Notification::assertNothingSent();
});

it('AC-046: a closed_positive task answers 422', function () {
    $actor = taskActorWith(['requestUpdate']);
    $closed = TaskStatus::query()->where('system_key', TaskStatusSystemKey::ClosedPositive->value)->firstOrFail();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($closed)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(422);
});

it('AC-047: a task in_validation answers 422', function () {
    $actor = taskActorWith(['requestUpdate']);
    $inValidation = TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($inValidation)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(422);
});

it('AC-048: a task in the pending phase answers 200 — isCompletable includes pending', function () {
    $actor = taskActorWith(['requestUpdate']);
    $pending = TaskStatus::factory()->group(TaskStatusGroup::Pending)->create();
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->inStatus($pending)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();
});

// ---------------------------------------------------------------------------
// AC-049..AC-052, AC-054 — recipients and message
// ---------------------------------------------------------------------------

it('AC-049: an empty recipient_ids answers 422 on recipient_ids', function () {
    $actor = taskActorWith(['requestUpdate']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipient_ids');
});

it('AC-050: a recipient who is neither assignee nor watcher answers 422, zero notifications sent', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $stranger = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$stranger->id]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('recipient_ids');

    Notification::assertNothingSent();
});

it('AC-051: an assignee and a watcher spunted together get exactly two notifications', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $watcher = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    $task->watchers()->attach($watcher->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", [
        'recipient_ids' => [$assignee->id, $watcher->id],
    ])->assertOk();

    Notification::assertSentTo([$assignee, $watcher], TaskUpdateRequested::class);
    Notification::assertCount(2);
});

it('AC-052: an un-spunted second assignee receives nothing (no automatic audience, D-11)', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $chosenAssignee = User::factory()->create();
    $otherAssignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach([$chosenAssignee->id, $otherAssignee->id]);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$chosenAssignee->id]])
        ->assertOk();

    Notification::assertSentTo($chosenAssignee, TaskUpdateRequested::class);
    Notification::assertNotSentTo($otherAssignee, TaskUpdateRequested::class);
});

it('AC-053 (endpoint): a request without a message still sends a full notification', function () {
    Notification::fake();
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertOk();

    Notification::assertSentTo($assignee, TaskUpdateRequested::class);
});

it('AC-054: a message over 2000 characters answers 422 on message', function () {
    $actor = taskActorWith(['requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", [
        'recipient_ids' => [$assignee->id],
        'message' => str_repeat('a', 2001),
    ])->assertStatus(422)->assertJsonValidationErrors('message');
});

// ---------------------------------------------------------------------------
// AC-058 — zero notifications on every refusal path
// ---------------------------------------------------------------------------

it('AC-058: a 403 refusal sends zero notifications', function () {
    Notification::fake();
    $actor = taskActorWith([]);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/request-update", ['recipient_ids' => [$assignee->id]])
        ->assertStatus(403);

    Notification::assertNothingSent();
});

// ---------------------------------------------------------------------------
// AC-059/AC-060 — the GET flag and the response shape
// ---------------------------------------------------------------------------

it('AC-059: permissions.actions.request_update is true for a pure watcher and false for a pure assignee', function () {
    $watcherActor = taskActorWith(['view', 'requestUpdate']);
    $assigneeActor = taskActorWith(['view', 'requestUpdate']);
    $task = Task::factory()->create();
    $task->watchers()->attach($watcherActor->id);
    $task->assignees()->attach($assigneeActor->id);

    Sanctum::actingAs($watcherActor);
    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.request_update', true);

    Sanctum::actingAs($assigneeActor);
    $this->getJson("/api/tasks/{$task->id}")
        ->assertOk()
        ->assertJsonPath('permissions.actions.request_update', false);
});

it('AC-060: a successful request-update response has the same shape as GET /api/tasks/{id}', function () {
    $actor = taskActorWith(['view', 'requestUpdate']);
    $assignee = User::factory()->create();
    $task = Task::factory()->forCreator($actor)->create();
    $task->assignees()->attach($assignee->id);
    Sanctum::actingAs($actor);

    $getResponse = $this->getJson("/api/tasks/{$task->id}")->assertOk();
    $actionResponse = $this->postJson("/api/tasks/{$task->id}/request-update", [
        'recipient_ids' => [$assignee->id],
    ])->assertOk();

    expect(array_keys($actionResponse->json()))->toBe(array_keys($getResponse->json()))
        ->and(array_keys($actionResponse->json('data')))->toBe(array_keys($getResponse->json('data')));

    // D-14: the Task itself is untouched by this action.
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'task_status_id' => $task->task_status_id]);
});
