<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Parent/child date-range coherence (spec 0123, D-7/D-8, AC-024..AC-028)
|--------------------------------------------------------------------------
|
| D-7 (child side): every non-null start_date/end_date of a Task with a
| parent must fall inside [parent.start_date, parent.end_date] — a null
| parent extreme leaves that side unconstrained. Evaluated on create
| unconditionally (a create carries no "submitted keys" partiality) and on
| update only when parent_task_id/start_date/end_date was submitted.
|
| D-8 (parent side, decision utente): a PATCH that leaves start_date/
| end_date dirty is refused if it would push a DIRECT child (ignoring
| visibility) outside the new range. Both guards run inside the write
| transaction: every 422 here is paired with an assertion that nothing
| moved.
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
// AC-024 — create a child outside the parent's range
// ---------------------------------------------------------------------------

it('AC-024: creating a child with end_date past the parent is 422 on end_date, nothing created', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-20',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Fuori dal padre',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'parent_task_id' => $parent->id,
        'end_date' => '2026-09-25',
    ])->assertStatus(422)->assertJsonValidationErrors('end_date');

    $this->assertDatabaseMissing('tasks', ['title' => 'Fuori dal padre']);
});

it('AC-024: creating a child with start_date before the parent is 422 on start_date, nothing created', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-20',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Fuori dal padre',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'parent_task_id' => $parent->id,
        'start_date' => '2026-09-05',
        'end_date' => '2026-09-15',
    ])->assertStatus(422)->assertJsonValidationErrors('start_date');

    $this->assertDatabaseMissing('tasks', ['title' => 'Fuori dal padre']);
});

// ---------------------------------------------------------------------------
// AC-025 — a null parent extreme does not constrain that side
// ---------------------------------------------------------------------------

it('AC-025: a parent with a null start_date does not constrain the child start_date', function () {
    $actor = taskActorWith(['create']);
    $requester = User::factory()->create();
    $assignee = User::factory()->create();
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => null,
        'end_date' => '2026-09-20',
    ]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', [
        'title' => 'Dentro il padre (start libero)',
        'requester_id' => $requester->id,
        'assignee_ids' => [$assignee->id],
        'parent_task_id' => $parent->id,
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-20',
    ])->assertCreated();

    $this->assertDatabaseHas('tasks', ['title' => 'Dentro il padre (start libero)']);
});

// ---------------------------------------------------------------------------
// AC-026 — PATCH of an existing child
// ---------------------------------------------------------------------------

it('AC-026: PATCHing a child end_date outside the parent is 422 on end_date, task unchanged', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-20',
    ]);
    $child = Task::factory()->forCreator($actor)->childOf($parent)->create([
        'start_date' => '2026-09-12',
        'end_date' => '2026-09-15',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$child->id}", ['end_date' => '2026-09-25'])
        ->assertStatus(422)->assertJsonValidationErrors('end_date');

    $this->assertDatabaseHas('tasks', ['id' => $child->id, 'end_date' => '2026-09-15']);
});

it('AC-026: PATCHing parent_task_id toward a parent whose range does not contain the child is 422 on the out-of-range date field', function () {
    $actor = taskActorWith(['view', 'update']);
    $newParent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-05',
    ]);
    $child = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-10',
        'end_date' => '2026-09-15',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$child->id}", ['parent_task_id' => $newParent->id])
        ->assertStatus(422)->assertJsonValidationErrors('start_date');

    $this->assertDatabaseHas('tasks', ['id' => $child->id, 'parent_task_id' => null]);
});

// ---------------------------------------------------------------------------
// AC-027 — parent restricting its range pushes children out
// ---------------------------------------------------------------------------

it('AC-027: PATCHing the parent end_date that would push two children out is 422 with the count, nothing moves', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-20',
    ]);
    $firstChild = Task::factory()->forCreator($actor)->childOf($parent)->create([
        'start_date' => '2026-09-05',
        'end_date' => '2026-09-18',
    ]);
    $secondChild = Task::factory()->forCreator($actor)->childOf($parent)->create([
        'start_date' => '2026-09-06',
        'end_date' => '2026-09-19',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/tasks/{$parent->id}", ['end_date' => '2026-09-17'])
        ->assertStatus(422)->assertJsonValidationErrors('end_date');

    expect($response->json('errors.end_date.0'))->toContain('2');

    $this->assertDatabaseHas('tasks', ['id' => $parent->id, 'end_date' => '2026-09-20']);
    $this->assertDatabaseHas('tasks', ['id' => $firstChild->id, 'end_date' => '2026-09-18']);
    $this->assertDatabaseHas('tasks', ['id' => $secondChild->id, 'end_date' => '2026-09-19']);
});

// ---------------------------------------------------------------------------
// AC-028 — a PATCH that does not touch the dates never evaluates D-8
// ---------------------------------------------------------------------------

it('AC-028: a PATCH of the parent that does not touch start_date/end_date is 200 even with children historically out of range', function () {
    $actor = taskActorWith(['view', 'update']);
    $parent = Task::factory()->forCreator($actor)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-20',
    ]);
    // Historically incoherent row (predates the rule, or written around it):
    // no sanatoria (D-8), so it must NOT block an unrelated PATCH.
    Task::factory()->forCreator($actor)->childOf($parent)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-10-05',
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$parent->id}", ['title' => 'Titolo aggiornato'])
        ->assertOk()->assertJsonPath('data.title', 'Titolo aggiornato');
});
