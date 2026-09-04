<?php

use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Permissions and meta of the `tasks` resource (spec 0101, AC-050..AC-055)
|--------------------------------------------------------------------------
|
| The five configurators are covered by the sibling
| tests/Feature/TaskConfig/TaskConfigPermissionsTest.php; the two
| cross-resource criteria (AC-050 permission count, AC-055 catalogue) are
| asserted here once, over all six resources at a time.
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
 * The six resource slugs of spec 0101 (D-1), in the order they were frozen.
 *
 * @var array<int, string>
 */
const TASK_MODULE_RESOURCES = ['tasks', 'task-statuses', 'task-types', 'task-categories', 'task-priorities', 'task-importances'];

// ---------------------------------------------------------------------------
// AC-050 — permissions:sync derives the abilities from the Policies alone
// ---------------------------------------------------------------------------

it('AC-050: permissions:sync creates 8 permissions per resource, plus tasks.viewAll, and nothing more', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (TASK_MODULE_RESOURCES as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            expect(Permission::query()->where('name', "{$resource}.{$ability}")->exists())
                ->toBeTrue("missing permission {$resource}.{$ability}");
        }
    }

    expect(Permission::query()->where('name', 'tasks.viewAll')->exists())->toBeTrue();

    // 9 for `tasks` (the 8 standard abilities + viewAll, D-9), 8 for each
    // configurator. `like 'tasks.%'` would also match nothing else: the five
    // configurators are `task-...` with a hyphen.
    expect(Permission::query()->where('name', 'like', 'tasks.%')->count())->toBe(9);

    foreach (array_slice(TASK_MODULE_RESOURCES, 1) as $resource) {
        expect(Permission::query()->where('name', 'like', "{$resource}.%")->count())
            ->toBe(8, "unexpected permission count for {$resource}");
    }
});

// ---------------------------------------------------------------------------
// AC-051 — 401/403 on every tasks endpoint, 200 on for-select without viewAny
// ---------------------------------------------------------------------------

it('AC-051: every tasks endpoint requires authentication (401)', function () {
    $task = Task::factory()->create();

    $this->getJson("/api/tasks/{$task->id}")->assertUnauthorized();
    $this->postJson('/api/tasks', [])->assertUnauthorized();
    $this->patchJson("/api/tasks/{$task->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/tasks/{$task->id}")->assertUnauthorized();
    $this->getJson('/api/tasks/for-select')->assertUnauthorized();
    $this->postJson('/api/tables/tasks/rows', [])->assertUnauthorized();
});

it('AC-051: GET show is 403 without tasks.view', function () {
    $actor = taskActorWith([]);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/tasks/{$task->id}")->assertForbidden();
});

it('AC-051: POST store is 403 without tasks.create, and no row is created', function () {
    $actor = taskActorWith([]);
    $status = TaskStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tasks', ['title' => 'Nope', 'task_status_id' => $status->id])->assertForbidden();

    expect(Task::query()->count())->toBe(0);
});

it('AC-051: PATCH update is 403 without tasks.update, and nothing is persisted', function () {
    $actor = taskActorWith(['view']);
    $task = Task::factory()->forCreator($actor)->create(['title' => 'Intatta']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tasks/{$task->id}", ['title' => 'Modificata'])->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'title' => 'Intatta']);
});

it('AC-051: DELETE destroy is 403 without tasks.delete, and the row survives', function () {
    $actor = taskActorWith(['view']);
    $task = Task::factory()->forCreator($actor)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tasks/{$task->id}")->assertForbidden();

    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
});

it('AC-051: GET tasks/for-select is 200 without tasks.viewAny (ADR 0011)', function () {
    $actor = taskActorWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/tasks/for-select')
        ->assertOk()
        ->assertJsonStructure(['items', 'pagination' => ['total', 'offset', 'limit', 'total_pages']]);
});

it('AC-051: tasks/for-select rows are still restricted by the visibility scope (D-9)', function () {
    $actor = taskActorWith([], withViewAll: false);
    $own = Task::factory()->forCreator($actor)->create(['title' => 'Mia']);
    $foreign = Task::factory()->create(['title' => 'Di altri']);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson('/api/tasks/for-select')->assertOk()->json('items'))->pluck('id');

    expect($ids)->toContain($own->id)->not->toContain($foreign->id);
});

it('AC-051: tasks/for-select honours exclude_id so the parent picker cannot offer the task itself (AC-082)', function () {
    $actor = taskActorWith([], withViewAll: false);
    $self = Task::factory()->forCreator($actor)->create(['title' => 'Io stesso']);
    $other = Task::factory()->forCreator($actor)->create(['title' => 'Un altro']);
    Sanctum::actingAs($actor);

    $ids = collect($this->getJson("/api/tasks/for-select?exclude_id={$self->id}")->assertOk()->json('items'))->pluck('id');

    expect($ids)->not->toContain($self->id)->toContain($other->id);
});

// ---------------------------------------------------------------------------
// AC-055 — the six resources are assignable from the role form
// ---------------------------------------------------------------------------

it('AC-055: the six resources appear in the permission catalogue with their permissions and fields', function () {
    $this->artisan('permissions:sync')->assertSuccessful();
    Permission::findOrCreate('roles.viewAny');
    $actor = User::factory()->create();
    $actor->givePermissionTo('roles.viewAny');
    Sanctum::actingAs($actor);

    $modules = collect($this->getJson('/api/authorization/permission-catalogue')->assertOk()->json('data.areas'))
        ->flatMap(fn (array $area): array => $area['resources'] ?? [])
        ->keyBy('resource');

    foreach (TASK_MODULE_RESOURCES as $resource) {
        expect($modules)->toHaveKey($resource)
            ->and($modules[$resource]['permissions'])->not->toBeEmpty()
            ->and($modules[$resource]['fields'])->not->toBeEmpty();
    }

    expect($modules['tasks']['permissions'])->toHaveCount(9)
        ->and($modules['task-statuses']['permissions'])->toHaveCount(8)
        ->and(collect($modules['tasks']['fields'])->pluck('key'))
        ->not->toContain('creator_id')
        ->not->toContain('completion_percentage');
});
