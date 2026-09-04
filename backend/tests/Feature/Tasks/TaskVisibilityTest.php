<?php

use App\Models\ExportRun;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskVisibilityScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Membership visibility of a Task (spec 0101, D-9, AC-060..AC-066)
|--------------------------------------------------------------------------
|
| A Task is visible to its CREATOR, REQUESTER, ASSIGNEE or WATCHER, or to
| anyone holding `tasks.viewAll`. The rule NARROWS and never widens: being a
| member does not stand in for `tasks.view`.
|
| Every actor here is built WITHOUT `tasks.viewAll` unless the test is about
| that ability, which is the opposite default from the other Task suites —
| this is the one file where "403" must be allowed to mean "not a member".
*/

if (! function_exists('taskActorWith')) {
    /**
     * An actor holding $abilities on `tasks`. `viewAll` is granted on top by
     * default so a 403 in a suite that is NOT about the membership scoping
     * always means "missing resource permission" — the separation
     * WorkOrderSecurityTest/WorkOrderVisibilityTest already draw. Pass
     * `withViewAll: false` to exercise the scope itself.
     *
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
     *
     * @param  array<int, string>  $abilities
     */
    function taskActorWith(array $abilities, bool $withViewAll = true): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity', 'viewAll'] as $ability) {
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
 * @return array<int, string>
 */
function visibleTaskTitles(): array
{
    $items = test()->postJson('/api/tables/tasks/rows', ['startRow' => 0, 'endRow' => 50])
        ->assertOk()->json('items');

    return collect($items)->pluck('title')->sort()->values()->all();
}

// ---------------------------------------------------------------------------
// AC-060 / AC-061 — the four memberships, and the viewAll bypass
// ---------------------------------------------------------------------------

it('AC-060: a task the actor has no link to is 403 on show and absent from the grid', function () {
    $actor = taskActorWith(['viewAny', 'view'], withViewAll: false);
    $foreign = Task::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$foreign->id}")->assertForbidden();

    expect(visibleTaskTitles())->not->toContain('Di altri');
});

it('AC-060: creator, requester, assignee and watcher each see the task', function () {
    $actor = taskActorWith(['viewAny', 'view'], withViewAll: false);

    $asCreator = Task::factory()->forCreator($actor)->create(['title' => 'Da creatore']);
    $asRequester = Task::factory()->create(['title' => 'Da richiedente', 'requester_id' => $actor->id]);
    $asAssignee = Task::factory()->create(['title' => 'Da assegnatario']);
    $asAssignee->assignees()->attach($actor->id);
    $asWatcher = Task::factory()->create(['title' => 'Da osservatore']);
    $asWatcher->watchers()->attach($actor->id);
    Task::factory()->create(['title' => 'Di altri']);

    Sanctum::actingAs($actor);

    foreach ([$asCreator, $asRequester, $asAssignee, $asWatcher] as $visible) {
        $this->getJson("/api/tasks/{$visible->id}")->assertOk();
    }

    expect(visibleTaskTitles())->toBe(['Da assegnatario', 'Da creatore', 'Da osservatore', 'Da richiedente']);
});

it('AC-061: tasks.viewAll shows every task in show and in the grid', function () {
    $actor = taskActorWith(['viewAny', 'view', 'viewAll']);
    Task::factory()->forCreator($actor)->create(['title' => 'Mia']);
    $foreign = Task::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$foreign->id}")->assertOk();

    expect(visibleTaskTitles())->toBe(['Di altri', 'Mia']);
});

it('AC-061: a super-admin sees every task with no tasks permission of their own', function () {
    taskActorWith([], withViewAll: false);
    $actor = User::factory()->create();
    $actor->assignRole(Role::findOrCreate('super-admin'));
    Task::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    expect(visibleTaskTitles())->toBe(['Di altri']);
});

// ---------------------------------------------------------------------------
// AC-062 / AC-063 — the rule narrows, and covers update/delete too
// ---------------------------------------------------------------------------

it('AC-062: being an assignee does NOT stand in for tasks.view', function () {
    $actor = taskActorWith([], withViewAll: false);
    $own = Task::factory()->create();
    $own->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$own->id}")->assertForbidden();
});

it('AC-063: PATCH on an out-of-scope task is 403 even with tasks.update, and nothing changes', function () {
    $actor = taskActorWith(['view', 'update'], withViewAll: false);
    $foreign = Task::factory()->create(['title' => 'Intatta']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$foreign->id}", ['title' => 'Modificata'])->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'title' => 'Intatta']);
});

it('AC-063: DELETE on an out-of-scope task is 403 even with tasks.delete, and the row survives', function () {
    $actor = taskActorWith(['view', 'delete'], withViewAll: false);
    $foreign = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$foreign->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $foreign->id]);
});

it('AC-063: the actor keeps writing the tasks they belong to', function () {
    $actor = taskActorWith(['view', 'update'], withViewAll: false);
    $own = Task::factory()->create(['title' => 'Prima']);
    $own->assignees()->attach($actor->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$own->id}", ['title' => 'Dopo'])->assertOk();

    $this->assertDatabaseHas('tasks', ['id' => $own->id, 'title' => 'Dopo']);
});

it('AC-063: the generic bulk-delete cannot reach an out-of-scope task', function () {
    $actor = taskActorWith(['viewAny', 'view', 'delete'], withViewAll: false);
    // A CONTROL row in the same call, which MUST disappear: without it, any
    // non-200 (403, 404, a 500 from a broken definition) would delete
    // nothing and the survival assertion below would pass for the wrong
    // reason. Same shape as AC-016 and AC-041/AC-042.
    $own = Task::factory()->forCreator($actor)->create();
    $foreign = Task::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/bulk-delete', ['ids' => [$own->id, $foreign->id]])->assertOk();

    $this->assertDatabaseMissing('tasks', ['id' => $own->id]);
    $this->assertDatabaseHas('tasks', ['id' => $foreign->id]);
});

// ---------------------------------------------------------------------------
// AC-064 — fail-closed on a null actor
// ---------------------------------------------------------------------------

it('AC-064: a null actor is scoped to an empty set, never left unrestricted', function () {
    Task::factory()->count(3)->create();

    $scoped = TaskVisibilityScope::scopeToActor(Task::query(), null);

    expect($scoped->count())->toBe(0)
        // The guard would be vacuous if the fixture itself were empty.
        ->and(Task::query()->count())->toBe(3);
});

it('AC-064: the fail-closed branch survives a query that already carries constraints', function () {
    $tasks = Task::factory()->count(2)->create();

    $scoped = TaskVisibilityScope::scopeToActor(Task::query()->whereKey($tasks->first()->id), null);

    expect($scoped->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-065 — the export inherits the scope of whoever asked for it
// ---------------------------------------------------------------------------

it('AC-065: the export contains only the tasks the requesting actor may see', function () {
    Storage::fake('local');
    $actor = taskActorWith(['viewAny', 'view', 'export'], withViewAll: false);
    $own = Task::factory()->create(['title' => 'Mia']);
    $own->watchers()->attach($actor->id);
    Task::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/tasks', [
        'format' => 'csv',
        'columns' => [['colId' => 'title', 'header' => 'Title']],
    ])->assertCreated();

    $run = ExportRun::findOrFail($response->json('data.export_run.id'))->fresh();
    $csv = Storage::disk('local')->get($run->file_path);

    expect($run->row_count)->toBe(1)
        ->and($csv)->toContain('Mia')->not->toContain('Di altri');
});

// ---------------------------------------------------------------------------
// AC-066 — the sub-task list in the detail is scoped too
// ---------------------------------------------------------------------------

it('AC-066: subtasks in the detail lists only the children visible to the actor', function () {
    $actor = taskActorWith(['view'], withViewAll: false);
    $parent = Task::factory()->forCreator($actor)->create();
    $visibleChild = Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'Figlio visibile']);
    $invisibleChild = Task::factory()->childOf($parent)->create(['title' => 'Figlio invisibile']);
    Sanctum::actingAs($actor);

    $subtasks = $this->getJson("/api/tasks/{$parent->id}")->assertOk()->json('data.subtasks');

    expect(collect($subtasks)->pluck('id')->all())->toBe([$visibleChild->id])
        ->and(collect($subtasks)->pluck('title')->all())->not->toContain('Figlio invisibile')
        // The invisible child still exists: it is hidden, not absent.
        ->and(Task::query()->whereKey($invisibleChild->id)->exists())->toBeTrue();
});

it('AC-066: with tasks.viewAll the detail lists every child', function () {
    $actor = taskActorWith(['view', 'viewAll']);
    $parent = Task::factory()->forCreator($actor)->create();
    Task::factory()->forCreator($actor)->childOf($parent)->create(['title' => 'Figlio proprio']);
    Task::factory()->childOf($parent)->create(['title' => 'Figlio altrui']);
    Sanctum::actingAs($actor);

    $subtasks = $this->getJson("/api/tasks/{$parent->id}")->assertOk()->json('data.subtasks');

    expect(collect($subtasks)->pluck('title')->sort()->values()->all())->toBe(['Figlio altrui', 'Figlio proprio']);
});
