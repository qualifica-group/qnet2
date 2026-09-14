<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| notes.deleteAny (spec 0126, D-2)
|--------------------------------------------------------------------------
|
| A new permission that lets an actor delete a note authored by someone
| else, PROVIDED they can still read the host record — NoteService::delete
| re-runs the same reauthorizeHost() check update() already applies, so
| deleteAny never reaches a note attached to a record the actor cannot see.
| It grants delete ONLY: update stays author-only (NotePolicy::update is
| untouched by this spec).
*/

uses(RefreshDatabase::class);

if (! function_exists('deleteAnyTaskActor')) {
    /**
     * @param  array<int, string>  $taskAbilities
     */
    function deleteAnyTaskActor(array $taskAbilities, bool $withDeleteAny = true): User
    {
        foreach (['view', 'viewAll', 'create'] as $ability) {
            Permission::findOrCreate("tasks.{$ability}");
        }
        Permission::findOrCreate('notes.create');
        Permission::findOrCreate('notes.deleteAny');

        $user = User::factory()->create();
        $user->givePermissionTo('notes.create');

        foreach ($taskAbilities as $ability) {
            $user->givePermissionTo("tasks.{$ability}");
        }

        if ($withDeleteAny) {
            $user->givePermissionTo('notes.deleteAny');
        }

        return $user;
    }
}

if (! function_exists('deleteAnyTaskNote')) {
    function deleteAnyTaskNote(Task $task, User $author): Note
    {
        return Note::factory()->for($author, 'author')->create([
            'notable_type' => 'task',
            'notable_id' => $task->id,
        ]);
    }
}

// ---------------------------------------------------------------------------
// AC-004 — notes.deleteAny + readable host -> 204/403 depending on the grant
// ---------------------------------------------------------------------------

it('AC-004: an actor with notes.deleteAny who can read the task deletes another member\'s note', function () {
    $author = deleteAnyTaskActor(['view']);
    $task = Task::factory()->forCreator($author)->create();
    $note = deleteAnyTaskNote($task, $author);

    $actor = deleteAnyTaskActor(['view']);
    $task->assignees()->attach($actor);
    Sanctum::actingAs($actor);

    $listed = $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertOk();
    expect($listed->json('data.0.can.delete'))->toBeTrue();

    $this->deleteJson("/api/notes/{$note->id}")->assertOk();

    $this->assertSoftDeleted('notes', ['id' => $note->id]);
});

it('AC-004: without notes.deleteAny, the same member gets 403 and can.delete is false', function () {
    $author = deleteAnyTaskActor(['view']);
    $task = Task::factory()->forCreator($author)->create();
    $note = deleteAnyTaskNote($task, $author);

    $actor = deleteAnyTaskActor(['view'], withDeleteAny: false);
    $task->assignees()->attach($actor);
    Sanctum::actingAs($actor);

    $listed = $this->getJson("/api/notes?entity_type=tasks&entity_id={$task->id}")->assertOk();
    expect($listed->json('data.0.can.delete'))->toBeFalse();

    $this->deleteJson("/api/notes/{$note->id}")->assertForbidden();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'deleted_at' => null]);
});

it('AC-004: request-management notable, an operator with notes.deleteAny deletes another operator\'s note', function () {
    Permission::findOrCreate('request-management.view');
    Permission::findOrCreate('notes.create');
    Permission::findOrCreate('notes.deleteAny');

    $author = User::factory()->create();
    $author->givePermissionTo(['request-management.view', 'notes.create']);

    $opportunity = Opportunity::factory()->create();
    Quote::factory()->for($opportunity)->create(['operator_id' => $author->id]);

    $note = Note::factory()->for($author, 'author')->create([
        'notable_type' => 'opportunity',
        'notable_id' => $opportunity->id,
    ]);

    $actor = User::factory()->create();
    $actor->givePermissionTo(['request-management.view', 'notes.create', 'notes.deleteAny']);
    Quote::factory()->for($opportunity)->create(['operator_id' => $actor->id]);

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/notes/{$note->id}")->assertOk();

    $this->assertSoftDeleted('notes', ['id' => $note->id]);
});

// ---------------------------------------------------------------------------
// AC-005 — notes.deleteAny does NOT bypass host readability, and never
// upgrades to update
// ---------------------------------------------------------------------------

it('AC-005: notes.deleteAny but the host task is NOT readable -> 403 and the note remains', function () {
    $author = deleteAnyTaskActor(['view']);
    $task = Task::factory()->forCreator($author)->create();
    $note = deleteAnyTaskNote($task, $author);

    // Holds notes.deleteAny but is not a member of the task and has no
    // tasks.viewAll: TaskNotable::authorizeRead() refuses.
    $actor = deleteAnyTaskActor([]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/notes/{$note->id}")->assertForbidden();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'deleted_at' => null]);
});

it('AC-005: notes.deleteAny does not grant PUT on a note authored by someone else', function () {
    $author = deleteAnyTaskActor(['view']);
    $task = Task::factory()->forCreator($author)->create();
    $note = deleteAnyTaskNote($task, $author);

    $actor = deleteAnyTaskActor(['view']);
    $task->assignees()->attach($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/notes/{$note->id}", ['body' => 'Hacked via deleteAny'])->assertForbidden();

    $this->assertDatabaseHas('notes', ['id' => $note->id, 'body' => $note->body]);
});
