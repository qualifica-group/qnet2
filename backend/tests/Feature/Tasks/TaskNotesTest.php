<?php

use App\Enums\TaskStatusGroup;
use App\Models\Note;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Notes\NoteEntityRegistry;
use App\Services\Tasks\TaskNotable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Collaborative notes on a Task (spec 0117, D-3..D-7, D-9, D-10)
|--------------------------------------------------------------------------
|
| The Task registers itself as a host of the agnostic notes component; no
| endpoint is added. Read access is the module's OWN rule — `tasks.view` AND
| the membership scope — and the mentionable set is that same rule read from
| the other end. Nothing here may restate the predicate: every expectation
| below is really a statement about TaskVisibilityScope.
|
| Every actor is built WITHOUT `tasks.viewAll` unless the test is about it,
| the same convention as TaskVisibilityTest: here a 403 must be allowed to
| mean "not a member".
*/

if (! function_exists('taskNoteActor')) {
    /**
     * @param  array<int, string>  $taskAbilities
     */
    function taskNoteActor(array $taskAbilities, bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll', 'manageAll', 'complete', 'validate', 'block', 'viewDocuments', 'requestUpdate'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }

        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-001 — the slug is registered
// ---------------------------------------------------------------------------

it('AC-001: `tasks` is registered in config/notes.php and resolves to TaskNotable', function () {
    expect(config('notes.notable_types'))->toHaveKey('tasks')
        ->and(config('notes.notable_types.tasks'))->toBe(TaskNotable::class)
        ->and(app(NoteEntityRegistry::class)->registeredTypes())
        ->toContain('tasks')
        ->toContain('request-management');
});

// ---------------------------------------------------------------------------
// AC-003 — the owner-side relation
// ---------------------------------------------------------------------------

it('AC-003: a Task owns its notes through the HasNotes morph relation', function () {
    $task = Task::factory()->create();
    $note = Note::factory()->create(['notable_type' => 'task', 'notable_id' => $task->id]);

    expect($task->notes()->pluck('notes.id')->all())->toBe([$note->id]);
});

// ---------------------------------------------------------------------------
// AC-004 / AC-005 — the four record roles read the thread
// ---------------------------------------------------------------------------

it('AC-004: the creator reads the thread of their own task', function () {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()
        ->assertJsonPath('meta.has_more', false);
});

it('AC-005: requester, assignee and watcher each read the thread of their task', function (string $role) {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->create($role === 'requester' ? ['requester_id' => $actor->id] : []);

    if ($role === 'assignee') {
        $task->assignees()->attach($actor);
    }

    if ($role === 'watcher') {
        $task->watchers()->attach($actor);
    }

    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertOk();
})->with(['requester', 'assignee', 'watcher']);

// ---------------------------------------------------------------------------
// AC-006 / AC-007 / AC-008 — the two halves of the gate
// ---------------------------------------------------------------------------

it('AC-006: tasks.viewAll reads the thread of a task the actor has no link to', function () {
    $actor = taskNoteActor(['view', 'viewAll']);
    $task = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertOk();
});

it('AC-007: tasks.view without membership and without viewAll is 403, and no note leaks', function () {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->create();
    Note::factory()->create(['notable_type' => 'task', 'notable_id' => $task->id, 'body' => 'Riservata']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertForbidden();

    expect($response->getContent())->not->toContain('Riservata');
});

it('AC-008: membership without tasks.view is 403 — the scope narrows, it does not grant', function () {
    $actor = taskNoteActor([]);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-009 / AC-010 / AC-011 — writing
// ---------------------------------------------------------------------------

it('AC-009: a member with notes.create writes a note attached to the task', function () {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $task->id,
        'body' => 'Primo commento',
    ])->assertCreated()->assertJsonPath('data.body', 'Primo commento');

    $this->assertDatabaseHas('notes', [
        'notable_type' => 'task',
        'notable_id' => $task->id,
        'user_id' => $actor->id,
    ]);
});

it('AC-010: a member WITHOUT notes.create is 403 and nothing is written', function () {
    $actor = taskNoteActor(['view'], canCreateNotes: false);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $task->id,
        'body' => 'Non deve passare',
    ])->assertForbidden();

    expect(Note::count())->toBe(0);
});

it('AC-011: a note is mutable by its author only, whatever the record role of the other member', function () {
    $author = taskNoteActor(['view']);
    $otherMember = taskNoteActor(['view']);

    $task = Task::factory()->forCreator($otherMember)->create();
    $task->assignees()->attach($author);

    Sanctum::actingAs($author);
    $noteId = $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $task->id,
        'body' => 'Scritta dall autore',
    ])->assertCreated()->json('data.id');

    // The creator of the Task is the most privileged record role there is,
    // and it still does not reach someone else's note (D-10).
    Sanctum::actingAs($otherMember);
    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Riscritta da altri'])->assertForbidden();
    $this->deleteJson("/api/notes/{$noteId}")->assertForbidden();

    Sanctum::actingAs($author);
    $this->patchJson("/api/notes/{$noteId}", ['body' => 'Corretta dall autore'])->assertOk();
    $this->deleteJson("/api/notes/{$noteId}")->assertOk();
});

// ---------------------------------------------------------------------------
// AC-012 / AC-013 / AC-014 — the mentionable set
// ---------------------------------------------------------------------------

it('AC-012: the mentionable set is exactly the record roles, plus viewAll holders and super-admins', function () {
    $creator = taskNoteActor(['view']);
    $requester = taskNoteActor(['view']);
    $assignee = taskNoteActor(['view']);
    $watcher = taskNoteActor(['view']);
    $viewAll = taskNoteActor(['view', 'viewAll']);
    $stranger = taskNoteActor(['view']);

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin'));

    $task = Task::factory()->forCreator($creator)->create(['requester_id' => $requester->id]);
    $task->assignees()->attach($assignee);
    $task->watchers()->attach($watcher);

    Sanctum::actingAs($creator);

    // The mention lookup answers the for-select envelope (ADR 0011):
    // `items`/`pagination`, with no `data` key at all.
    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->json('items'))->pluck('id')->sort()->values()->all();

    expect($ids)->toBe(
        collect([$creator->id, $requester->id, $assignee->id, $watcher->id, $viewAll->id, $superAdmin->id])
            ->sort()->values()->all()
    )->and($ids)->not->toContain($stranger->id);
});

it('AC-013: a deactivated assignee is not mentionable', function () {
    $creator = taskNoteActor(['view']);
    $assignee = taskNoteActor(['view']);
    $assignee->update(['is_active' => false]);

    $task = Task::factory()->forCreator($creator)->create();
    $task->assignees()->attach($assignee);

    Sanctum::actingAs($creator);

    $ids = collect($this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->not->toContain($assignee->id);
});

it('AC-014: the endpoint answers 200 on an unseeded catalogue instead of throwing PermissionDoesNotExist', function () {
    $creator = taskNoteActor(['view']);
    $task = Task::factory()->forCreator($creator)->create();

    // The state of an environment where the optional tier was never synced.
    // Only `tasks.viewAll` goes: dropping `tasks.view` too would make
    // authorizeRead refuse first, and the test would no longer be about the
    // mention query at all.
    Permission::query()->where('name', 'tasks.viewAll')->delete();
    app()['cache']->forget('spatie.permission.cache');

    Sanctum::actingAs($creator);

    $this->getJson("/api/notes/mentionable-users?entity_type=tasks&entity_id={$task->id}")->assertOk();
});

// ---------------------------------------------------------------------------
// AC-015 / AC-016 — a Task has no scoping unit
// ---------------------------------------------------------------------------

it('AC-015: ownsQuote is always false, quoteScopes always empty, meta.quotes empty', function () {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    $notable = app(TaskNotable::class);

    expect($notable->ownsQuote($task, 1))->toBeFalse()
        ->and($notable->quoteScopes($task))->toBe([]);

    Sanctum::actingAs($actor);

    $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")
        ->assertOk()
        ->assertJsonPath('meta.quotes', []);
});

it('AC-016: a note submitted with quote_id is 422 and nothing is written', function () {
    $actor = taskNoteActor(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $task->id,
        'body' => 'Con scope inesistente',
        'quote_id' => 1,
    ])->assertStatus(422);

    expect(Note::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-017 — the deep link is per-recipient
// ---------------------------------------------------------------------------

it('AC-017: the deep link is /tasks/{id} for a recipient who sees the task, null otherwise', function () {
    $member = taskNoteActor(['view']);
    $stranger = taskNoteActor(['view']);
    $unpermitted = taskNoteActor([]);

    $task = Task::factory()->forCreator($member)->create();
    $notable = app(TaskNotable::class);

    expect($notable->deepLinkPath($task, $member, null))->toBe("/tasks/{$task->id}")
        ->and($notable->deepLinkPath($task, $stranger, null))->toBeNull()
        ->and($notable->deepLinkPath($task, $unpermitted, null))->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-019 — notes are an OPERATIVE action: the write lock does not reach them
// ---------------------------------------------------------------------------

it('AC-019: a member writes a note on a frozen task (blocked, closed, in validation)', function (string $frozen) {
    $actor = taskNoteActor(['view']);

    $task = match ($frozen) {
        'blocked' => Task::factory()->forCreator($actor)->create(['is_blocked' => true]),
        'closed' => Task::factory()->forCreator($actor)
            ->inStatus(TaskStatus::factory()->group(TaskStatusGroup::ClosedPositive)->create())->create(),
        'in_validation' => Task::factory()->forCreator($actor)
            ->inStatus(TaskStatus::factory()->group(TaskStatusGroup::InValidation)->create())->create(),
    };

    Sanctum::actingAs($actor);

    $this->postJson('/api/notes', [
        'entity_type' => 'tasks',
        'entity_id' => $task->id,
        'body' => 'Commento su task congelato',
    ])->assertCreated();
})->with(['blocked', 'closed', 'in_validation']);
