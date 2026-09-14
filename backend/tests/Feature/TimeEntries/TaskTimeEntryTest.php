<?php

use App\Models\Registry;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Task-scoped segnatempo (spec 0122, D-9, AC-022, AC-023)
|--------------------------------------------------------------------------
*/

if (! function_exists('taskTimeEntryActor')) {
    /**
     * An actor holding `tasks.view` (D-9's own resource-level gate) plus the
     * given `time-entries.*` abilities.
     *
     * @param  array<int, string>  $timeEntryAbilities
     */
    function taskTimeEntryActor(array $timeEntryAbilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'exportMonthly', 'manageAll', 'viewAll'] as $ability) {
            Permission::findOrCreate("time-entries.{$ability}");
        }

        $user = User::factory()->create();
        $user->givePermissionTo('tasks.view');

        foreach ($timeEntryAbilities as $ability) {
            $user->givePermissionTo("time-entries.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-022 — assignee POSTs, title/links come from the Task; observer is 403
// ---------------------------------------------------------------------------

it('AC-022: an assignee POSTs and gets 201 with the Task\'s title and links', function () {
    $actor = taskTimeEntryActor(['create']);
    $registry = Registry::factory()->create();
    $task = Task::factory()->create(['title' => 'Task X', 'registry_id' => $registry->id]);
    $task->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/tasks/{$task->id}/time-entries", [
        'date' => '2026-09-14',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 60,
    ])->assertCreated();

    expect($response->json('data.title'))->toBe('Task X')
        ->and($response->json('data.registry.id'))->toBe($registry->id)
        ->and($response->json('data.task.id'))->toBe($task->id)
        ->and($response->json('data.user.id'))->toBe($actor->id);

    $this->assertDatabaseHas('time_entries', [
        'id' => $response->json('data.id'),
        'task_id' => $task->id,
        'title' => 'Task X',
        'user_id' => $actor->id,
    ]);
});

it('AC-022: a watcher (osservatore) gets 403', function () {
    $actor = taskTimeEntryActor(['create']);
    $task = Task::factory()->create();
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->postJson("/api/tasks/{$task->id}/time-entries", [
        'date' => '2026-09-14',
        'task_type_id' => TaskType::factory()->create()->id,
        'minutes' => 60,
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-023 — list of both users' entries, total_minutes, can_create, 403
// ---------------------------------------------------------------------------

it('AC-023: GET returns both users\' entries, total_minutes summed, can_create true for an assignee', function () {
    $actor = taskTimeEntryActor(['viewAny', 'create']);
    $task = Task::factory()->create();
    $task->assignees()->attach($actor->id);
    $entryA = TimeEntry::factory()->forUser($actor)->create(['task_id' => $task->id, 'minutes' => 30]);
    $other = User::factory()->create();
    $entryB = TimeEntry::factory()->forUser($other)->create(['task_id' => $task->id, 'minutes' => 45]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/tasks/{$task->id}/time-entries")->assertOk();

    expect($response->json('data.total_minutes'))->toBe(75)
        ->and($response->json('data.can_create'))->toBeTrue();

    $ids = collect($response->json('data.items'))->pluck('id');
    expect($ids->all())->toContain($entryA->id, $entryB->id);
});

it('AC-023: can_create is false for a watcher even with time-entries.create', function () {
    $actor = taskTimeEntryActor(['viewAny', 'create']);
    $task = Task::factory()->create();
    $task->watchers()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}/time-entries")
        ->assertOk()
        ->assertJsonPath('data.can_create', false);
});

it('AC-023: without visibility on the Task the request is 403', function () {
    $actor = taskTimeEntryActor(['viewAny']);
    $task = Task::factory()->create(); // unrelated creator, no membership, no tasks.viewAll
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}/time-entries")->assertForbidden();
});
