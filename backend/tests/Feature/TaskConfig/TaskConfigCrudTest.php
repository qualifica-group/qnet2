<?php

use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| The five Task configurators, CRUD (spec 0101, AC-040/041/045/046)
|--------------------------------------------------------------------------
|
| D-4 makes the five modules the SAME module with a different resource name,
| so every criterion here is written once and datasetted over all five. A
| rule that held for four of them and silently not for the fifth is exactly
| what a per-module copy-paste suite would miss.
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
 * The five configurators: resource slug, model class, table, FK on `tasks`,
 * and the resource name the 409 delete guard puts in its message (D-8b).
 *
 * @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
 */
dataset('taskConfigurators', [
    'task-statuses' => ['task-statuses', TaskStatus::class, 'task_statuses', 'task_status_id', 'task status'],
    'task-types' => ['task-types', TaskType::class, 'task_types', 'task_type_id', 'task type'],
    'task-categories' => ['task-categories', TaskCategory::class, 'task_categories', 'task_category_id', 'task category'],
    'task-priorities' => ['task-priorities', TaskPriority::class, 'task_priorities', 'task_priority_id', 'task priority'],
    'task-importances' => ['task-importances', TaskImportance::class, 'task_importances', 'task_importance_id', 'task importance'],
]);

// ---------------------------------------------------------------------------
// store / update happy path
// ---------------------------------------------------------------------------

it('store: 201 with name, color and icon, and sort_order assigned server-side', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create', 'view']));

    $response = $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['icon' => 'flag', 'description' => 'Descrizione']))
        ->assertCreated()
        ->assertJsonPath('data.name', 'Nuova voce')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.icon', 'flag');

    expect($response->json('data.sort_order'))->toBeInt();

    $this->assertDatabaseHas($table, ['id' => $response->json('data.id'), 'name' => 'Nuova voce', 'icon' => 'flag']);
})->with('taskConfigurators');

it('store: 422 on a duplicate name', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));
    $modelClass::factory()->create(['name' => 'Gia esistente']);

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['name' => 'Gia esistente']))
        ->assertStatus(422)->assertJsonValidationErrors('name');
})->with('taskConfigurators');

// ---------------------------------------------------------------------------
// AC-045 — system_key and sort_order are never accepted
// ---------------------------------------------------------------------------

it('AC-045: store rejects sort_order with a 422 on that key', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['sort_order' => 5]))
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');
})->with('taskConfigurators');

it('AC-045: store rejects system_key with a 422 on that key', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['system_key' => 'open']))
        ->assertStatus(422)->assertJsonValidationErrors('system_key');
})->with('taskConfigurators');

it('AC-045: update rejects sort_order and system_key, and nothing is persisted', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $row = $modelClass::factory()->create(['name' => 'Invariata']);
    $originalOrder = $row->sort_order;

    $this->patchJson("/api/{$resource}/{$row->id}", ['sort_order' => 999])
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');

    $this->patchJson("/api/{$resource}/{$row->id}", ['system_key' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('system_key');

    $this->assertDatabaseHas($table, ['id' => $row->id, 'name' => 'Invariata', 'sort_order' => $originalOrder]);
})->with('taskConfigurators');

// ---------------------------------------------------------------------------
// AC-046 — color, icon and percentage are checked against allow-lists
// ---------------------------------------------------------------------------

it('AC-046: a color outside the badge palette is 422', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['color' => '#ff0000']))
        ->assertStatus(422)->assertJsonValidationErrors('color');

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['color' => 'fuchsia']))
        ->assertStatus(422)->assertJsonValidationErrors('color');
})->with('taskConfigurators');

it('AC-046: an icon outside the curated lucide catalogue is 422', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['icon' => 'not-a-real-icon']))
        ->assertStatus(422)->assertJsonValidationErrors('icon');
})->with('taskConfigurators');

it('AC-046: every palette token and every catalogue icon is accepted', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create', 'view']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['name' => 'Con slate', 'color' => 'slate', 'icon' => 'timer']))
        ->assertCreated();
    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['name' => 'Con pink', 'color' => 'pink', 'icon' => 'zap']))
        ->assertCreated();
})->with('taskConfigurators');

it('AC-046: completion_percentage outside 0..100 is 422 (task-statuses only carries it)', function () {
    Sanctum::actingAs(taskConfigActorWith('task-statuses', ['create']));

    foreach ([101, -1, 255] as $percentage) {
        $this->postJson('/api/task-statuses', taskConfigStorePayload('task-statuses', ['completion_percentage' => $percentage]))
            ->assertStatus(422)->assertJsonValidationErrors('completion_percentage');
    }

    $this->postJson('/api/task-statuses', taskConfigStorePayload('task-statuses', ['name' => 'Zero', 'completion_percentage' => 0]))->assertCreated();
    $this->postJson('/api/task-statuses', taskConfigStorePayload('task-statuses', ['name' => 'Cento', 'completion_percentage' => 100]))->assertCreated();
});

it('AC-045: completion_percentage never becomes data on the four pure lookups (D-4)', function (string $resource, string $modelClass, string $table) {
    if ($resource === 'task-statuses') {
        // The one configurator that DOES carry the column; its range rules
        // are asserted by the AC-046 test above.
        expect(Schema::hasColumn($table, 'completion_percentage'))->toBeTrue();

        return;
    }

    // AC-045, as finally worded (the "prohibit it too" rectification was
    // RETRACTED 2026-09-04): what the five modules refuse uniformly is
    // SERVER-MANAGED state (`system_key`, `sort_order`). Here
    // `completion_percentage` is neither server-managed nor part of the
    // contract — it is an unknown key like `foo` would be. So the criterion
    // guarantees that it never becomes DATA on these four tables, NOT that
    // it is rejected.
    //
    // This test therefore asserts the invariant and deliberately does NOT
    // pin the status code: a 422 (`prohibited`) and a 201 (ignored) both
    // satisfy the contract, and pinning either would make the suite fail on
    // a legitimate implementation choice rather than on a real defect.
    expect(Schema::hasColumn($table, 'completion_percentage'))->toBeFalse();

    Sanctum::actingAs(taskConfigActorWith($resource, ['create', 'update', 'view']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['completion_percentage' => 50]));

    $row = $modelClass::factory()->create(['name' => 'Invariata']);
    $this->patchJson("/api/{$resource}/{$row->id}", ['completion_percentage' => 50]);

    // Whatever the two calls answered, no row on this table carries the
    // attribute, and the one that already existed is untouched.
    foreach ($modelClass::query()->get() as $persisted) {
        expect($persisted->getAttributes())->not->toHaveKey('completion_percentage');
    }

    expect($row->fresh()->name)->toBe('Invariata');
})->with('taskConfigurators');

// ---------------------------------------------------------------------------
// AC-040 / AC-041 — the delete guard
// ---------------------------------------------------------------------------

it('AC-040: DELETE of a row no task uses returns 204 and removes it', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['delete']));
    $row = $modelClass::factory()->create();

    $this->deleteJson("/api/{$resource}/{$row->id}")->assertNoContent();

    $this->assertDatabaseMissing($table, ['id' => $row->id]);
})->with('taskConfigurators');

it('AC-041: DELETE of a row a task uses is 409, naming the resource; neither the row nor the task is removed', function (string $resource, string $modelClass, string $table, string $foreignKey, string $resourceName) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['delete']));
    $row = $modelClass::factory()->create();
    $task = Task::factory()->create([$foreignKey => $row->id]);

    $this->deleteJson("/api/{$resource}/{$row->id}")
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', "This {$resourceName} is used by a task and cannot be deleted.");

    $this->assertDatabaseHas($table, ['id' => $row->id]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id]);
})->with('taskConfigurators');

it('AC-041: the generic bulk-delete applies the same in-use guard', function (string $resource, string $modelClass, string $table, string $foreignKey) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['viewAny', 'delete']));
    $used = $modelClass::factory()->create();
    $free = $modelClass::factory()->create();
    Task::factory()->create([$foreignKey => $used->id]);

    $this->postJson("/api/tables/{$resource}/bulk-delete", ['ids' => [$used->id, $free->id]]);

    $this->assertDatabaseHas($table, ['id' => $used->id]);
    $this->assertDatabaseMissing($table, ['id' => $free->id]);
})->with('taskConfigurators');
