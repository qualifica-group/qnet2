<?php

use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Authorization of the five configurators (spec 0101, AC-051)
|--------------------------------------------------------------------------
|
| One dataset over the five resources, so a gate that is present on four of
| them and missing on the fifth cannot hide. The `tasks` half of AC-051, and
| the two cross-resource criteria AC-050/AC-055, live in the sibling
| tests/Feature/Tasks/TaskPermissionsTest.php.
*/

if (! function_exists('taskConfigActorWith')) {
    /**
     * An actor holding $abilities on ONE of the five task configurators.
     * Duplicated (guarded) across the suites that need it, following the
     * repo idiom for shared Pest helpers (see workOrderUserWith).
     *
     * @param  array<int, string>  $abilities
     */
    function taskConfigActorWith(string $resource, array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("{$resource}.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("{$resource}.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('taskConfigStorePayload')) {
    /**
     * The minimum valid store payload for $resource: `task-statuses` is the
     * only one carrying `completion_percentage` (D-4).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function taskConfigStorePayload(string $resource, array $overrides = []): array
    {
        $payload = ['name' => 'Nuova voce', 'color' => 'blue'];

        if ($resource === 'task-statuses') {
            $payload['completion_percentage'] = 40;
        }

        return [...$payload, ...$overrides];
    }
}

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('taskConfiguratorResources', [
    'task-statuses' => ['task-statuses', TaskStatus::class, 'task_statuses'],
    'task-types' => ['task-types', TaskType::class, 'task_types'],
    'task-categories' => ['task-categories', TaskCategory::class, 'task_categories'],
    'task-priorities' => ['task-priorities', TaskPriority::class, 'task_priorities'],
    'task-importances' => ['task-importances', TaskImportance::class, 'task_importances'],
]);

// ---------------------------------------------------------------------------
// AC-051 — 401 unauthenticated
// ---------------------------------------------------------------------------

it('AC-051: every endpoint of the configurator requires authentication (401)', function (string $resource, string $modelClass) {
    $row = $modelClass::factory()->create();

    $this->getJson("/api/{$resource}/{$row->id}")->assertUnauthorized();
    $this->postJson("/api/{$resource}", [])->assertUnauthorized();
    $this->patchJson("/api/{$resource}/{$row->id}", [])->assertUnauthorized();
    $this->deleteJson("/api/{$resource}/{$row->id}")->assertUnauthorized();
    $this->getJson("/api/{$resource}/for-select")->assertUnauthorized();
    $this->postJson("/api/tables/{$resource}/rows", [])->assertUnauthorized();
})->with('taskConfiguratorResources');

// ---------------------------------------------------------------------------
// AC-051 — 403 without the matching permission, on each of the four verbs
// ---------------------------------------------------------------------------

it('AC-051: GET show is 403 without the view permission', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $row = $modelClass::factory()->create();

    $this->getJson("/api/{$resource}/{$row->id}")->assertForbidden();
})->with('taskConfiguratorResources');

it('AC-051: POST store is 403 without the create permission, and no row is created', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $countBefore = $modelClass::query()->count();

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource))->assertForbidden();

    expect($modelClass::query()->count())->toBe($countBefore);
})->with('taskConfiguratorResources');

it('AC-051: PATCH update is 403 without the update permission, and nothing is persisted', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $row = $modelClass::factory()->create(['name' => 'Invariata']);

    $this->patchJson("/api/{$resource}/{$row->id}", ['name' => 'Modificata'])->assertForbidden();

    $this->assertDatabaseHas($table, ['id' => $row->id, 'name' => 'Invariata']);
})->with('taskConfiguratorResources');

it('AC-051: DELETE destroy is 403 without the delete permission, and the row survives', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $row = $modelClass::factory()->create();

    $this->deleteJson("/api/{$resource}/{$row->id}")->assertForbidden();

    $this->assertDatabaseHas($table, ['id' => $row->id]);
})->with('taskConfiguratorResources');

it('AC-051: the grid and the meta are 403 without the viewAny permission', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));

    $this->postJson("/api/tables/{$resource}/rows", ['startRow' => 0, 'endRow' => 25])->assertForbidden();
    $this->getJson("/api/tables/{$resource}/columns")->assertForbidden();
    $this->getJson("/api/meta/{$resource}")->assertForbidden();
})->with('taskConfiguratorResources');

// ---------------------------------------------------------------------------
// AC-051 — for-select answers without viewAny (ADR 0011)
// ---------------------------------------------------------------------------

it('AC-051: GET for-select is 200 without the viewAny permission', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $modelClass::factory()->count(2)->create();

    $this->getJson("/api/{$resource}/for-select")
        ->assertOk()
        ->assertJsonStructure(['items' => [['id', 'label']], 'pagination' => ['total', 'offset', 'limit', 'total_pages']]);
})->with('taskConfiguratorResources');

it('AC-051: for-select excludes inactive rows and orders by sort_order', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $late = $modelClass::factory()->create(['name' => 'Zeta ordinata', 'sort_order' => 900, 'is_active' => true]);
    $early = $modelClass::factory()->create(['name' => 'Alfa ordinata', 'sort_order' => 901, 'is_active' => true]);
    $inactive = $modelClass::factory()->create(['name' => 'Disattivata', 'is_active' => false]);

    $ids = collect($this->getJson("/api/{$resource}/for-select")->assertOk()->json('items'))->pluck('id');

    expect($ids)->not->toContain($inactive->id);

    $ordered = $ids->filter(fn (int $id): bool => in_array($id, [$late->id, $early->id], true))->values()->all();

    expect($ordered)->toBe([$late->id, $early->id]);
})->with('taskConfiguratorResources');

it('AC-051: for-select rejects a limit above 100', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));

    $this->getJson("/api/{$resource}/for-select?limit=101")
        ->assertStatus(422)->assertJsonValidationErrors('limit');
})->with('taskConfiguratorResources');

// ---------------------------------------------------------------------------
// AC-051 — the meta field catalogue of each configurator
// ---------------------------------------------------------------------------

it('AC-051: the meta exposes the configurator fields, with completion_percentage only on task-statuses', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['viewAny', 'create']));

    $keys = collect($this->getJson("/api/meta/{$resource}")->assertOk()->json('data.fields'))->pluck('key')->all();

    $expected = ['name', 'description', 'color', 'icon', 'is_active'];

    if ($resource === 'task-statuses') {
        $expected[] = 'completion_percentage';
    }

    expect($keys)->toEqualCanonicalizing($expected)
        // sort_order and system_key are server-managed: never permissionable,
        // never submittable (AC-045).
        ->and($keys)->not->toContain('sort_order')
        ->and($keys)->not->toContain('system_key');
})->with('taskConfiguratorResources');
