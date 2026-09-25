<?php

use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The `tasks` grid — tree/hierarchical row scoping (spec 0157, AC-001)
|--------------------------------------------------------------------------
|
| `tree=true` with no `treeParentId` restricts POST /api/tables/tasks/rows
| to root Task; `treeParentId=N` restricts it to N's direct children (D-1).
| Both keep every other active filter/search/sort exactly as Analitica. A
| non-existent parent is 422; a parent the actor cannot see is 403. Every
| other domain rejects the two keys outright (contract: "nessun cambio di
| comportamento per gli altri domini"), and only `tasks` accepts a 500-row
| SSRM block (spec 0157, D-4).
*/

if (! function_exists('taskActorWith')) {
    /**
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom (see TaskTableTest.php).
     *
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

if (! function_exists('taskTreeTitles')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<int, string>
     */
    function taskTreeTitles(array $overrides): array
    {
        $items = test()->postJson('/api/tables/tasks/rows', array_merge([
            'startRow' => 0, 'endRow' => 50,
            'advancedFilters' => ['assignment' => ['visible']],
        ], $overrides))->assertOk()->json('items');

        return collect($items)->pluck('title')->sort()->values()->all();
    }
}

it('AC-001: tree=true with no treeParentId returns only root Task', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $root = Task::factory()->forCreator($actor)->create(['title' => 'Root']);
    Task::factory()->forCreator($actor)->childOf($root)->create(['title' => 'Child']);
    Sanctum::actingAs($actor);

    expect(taskTreeTitles(['tree' => true]))->toBe(['Root']);
});

it('AC-001: treeParentId returns the direct children only, never a grandchild', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $root = Task::factory()->forCreator($actor)->create(['title' => 'Root']);
    $child = Task::factory()->forCreator($actor)->childOf($root)->create(['title' => 'Child']);
    Task::factory()->forCreator($actor)->childOf($child)->create(['title' => 'Grandchild']);
    Sanctum::actingAs($actor);

    expect(taskTreeTitles(['treeParentId' => $root->id]))->toBe(['Child']);
});

it('AC-001: treeParentId without an explicit tree flag is still tree mode', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $root = Task::factory()->forCreator($actor)->create(['title' => 'Root']);
    Task::factory()->forCreator($actor)->childOf($root)->create(['title' => 'Child']);
    Sanctum::actingAs($actor);

    expect(taskTreeTitles(['treeParentId' => null]))->toBe(['Root']);
});

it('D-1: a root that does not match the active filter never surfaces its matching child', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $matchingRoot = Task::factory()->forCreator($actor)->create(['title' => 'Alfa speciale']);
    $nonMatchingRoot = Task::factory()->forCreator($actor)->create(['title' => 'Beta comune']);
    Task::factory()->forCreator($actor)->childOf($nonMatchingRoot)->create(['title' => 'Gamma speciale']);
    Sanctum::actingAs($actor);

    expect(taskTreeTitles(['tree' => true, 'search' => 'speciale']))->toBe(['Alfa speciale']);
});

it('AC-001: a non-existent treeParentId is 422', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    test()->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'treeParentId' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('treeParentId');
});

it('AC-001: a treeParentId the actor cannot see is 403', function () {
    $owner = taskActorWith(['viewAny', 'view']);
    $foreign = Task::factory()->forCreator($owner)->create();

    $actor = taskActorWith(['viewAny', 'view'], withViewAll: false);
    Sanctum::actingAs($actor);

    test()->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 25, 'treeParentId' => $foreign->id,
    ])->assertForbidden();
});

it('Analitica (no tree keys) is untouched: roots and children both appear', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    $root = Task::factory()->forCreator($actor)->create(['title' => 'Root']);
    Task::factory()->forCreator($actor)->childOf($root)->create(['title' => 'Child']);
    Sanctum::actingAs($actor);

    expect(taskTreeTitles([]))->toBe(['Child', 'Root']);
});

it('every other domain rejects tree/treeParentId with a 422', function () {
    Permission::findOrCreate('users.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.viewAny');
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25, 'tree' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('tree');

    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25, 'treeParentId' => null,
    ])->assertStatus(422)->assertJsonValidationErrors('tree');
});

it('spec 0157, D-4: tasks accepts a 500-row SSRM block, five times the shared cap', function () {
    $actor = taskActorWith(['viewAny', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 500,
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertOk();

    $this->postJson('/api/tables/tasks/rows', [
        'startRow' => 0, 'endRow' => 501,
        'advancedFilters' => ['assignment' => ['visible']],
    ])->assertStatus(422)->assertJsonValidationErrors('endRow');
});

it('every other domain keeps the shared 100-row cap', function () {
    Permission::findOrCreate('users.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('users.viewAny');
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 500,
    ])->assertStatus(422)->assertJsonValidationErrors('endRow');
});
