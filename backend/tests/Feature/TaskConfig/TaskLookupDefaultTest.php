<?php

use App\Models\TaskImportance;
use App\Models\TaskPriority;
use App\Models\TaskType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| `is_default` on task-types / task-priorities / task-importances (spec
| 0154, D-8)
|--------------------------------------------------------------------------
|
| task-categories does NOT carry this column (it nests instead, D-1), so it
| is deliberately excluded from `defaultLookups` below.
*/

/**
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('defaultLookups', [
    'task-types' => ['task-types', TaskType::class, 'task_types'],
    'task-priorities' => ['task-priorities', TaskPriority::class, 'task_priorities'],
    'task-importances' => ['task-importances', TaskImportance::class, 'task_importances'],
]);

// ---------------------------------------------------------------------------
// store: at most one default row, enforced by clearing the others
// ---------------------------------------------------------------------------

it('store: is_default true is persisted and exposed by the resource', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $response = $this->postJson("/api/{$resource}", ['name' => 'Prima', 'color' => 'blue', 'is_default' => true])
        ->assertCreated()
        ->assertJsonPath('data.is_default', true);

    $this->assertDatabaseHas($table, ['id' => $response->json('data.id'), 'is_default' => true]);
})->with('defaultLookups');

it('store: omitting is_default defaults to false', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", ['name' => 'Seconda', 'color' => 'blue'])
        ->assertCreated()
        ->assertJsonPath('data.is_default', false);
})->with('defaultLookups');

it('store: a new default row clears the previous one, in the same request', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));
    $previous = $modelClass::factory()->default()->create(['name' => 'Vecchia predefinita']);

    $this->postJson("/api/{$resource}", ['name' => 'Nuova predefinita', 'color' => 'blue', 'is_default' => true])
        ->assertCreated();

    expect($previous->fresh()->is_default)->toBeFalse()
        ->and($modelClass::query()->where('is_default', true)->count())->toBe(1);
})->with('defaultLookups');

it('store: is_default true on an explicitly inactive row is 422', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create']));

    $this->postJson("/api/{$resource}", ['name' => 'Inattiva', 'color' => 'blue', 'is_default' => true, 'is_active' => false])
        ->assertStatus(422);
})->with('defaultLookups');

// ---------------------------------------------------------------------------
// update: same "at most one" + "must be active" invariants
// ---------------------------------------------------------------------------

it('update: setting is_default on another row clears the current one', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $current = $modelClass::factory()->default()->create();
    $candidate = $modelClass::factory()->create(['is_default' => false]);

    $this->patchJson("/api/{$resource}/{$candidate->id}", ['is_default' => true])->assertOk();

    expect($current->fresh()->is_default)->toBeFalse()
        ->and($candidate->fresh()->is_default)->toBeTrue();
})->with('defaultLookups');

it('update: is_default true on a row whose is_active is being set to false in the SAME request is 422', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $row = $modelClass::factory()->create(['is_active' => true, 'is_default' => false]);

    $this->patchJson("/api/{$resource}/{$row->id}", ['is_default' => true, 'is_active' => false])
        ->assertStatus(422);

    expect($row->fresh()->is_default)->toBeFalse();
})->with('defaultLookups');

it('update: is_default true on an ALREADY inactive row (is_active not resubmitted) is 422', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $row = $modelClass::factory()->create(['is_active' => false, 'is_default' => false]);

    $this->patchJson("/api/{$resource}/{$row->id}", ['is_default' => true])->assertStatus(422);

    expect($row->fresh()->is_default)->toBeFalse();
})->with('defaultLookups');

it('update: deactivating the current default without unsetting is_default is 422', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $default = $modelClass::factory()->default()->create();

    $this->patchJson("/api/{$resource}/{$default->id}", ['is_active' => false])->assertStatus(422);

    expect($default->fresh()->is_active)->toBeTrue();
})->with('defaultLookups');

it('update: a row can be both deactivated and un-defaulted in the same request', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $default = $modelClass::factory()->default()->create();

    $this->patchJson("/api/{$resource}/{$default->id}", ['is_active' => false, 'is_default' => false])->assertOk();

    expect($default->fresh()->is_active)->toBeFalse()
        ->and($default->fresh()->is_default)->toBeFalse();
})->with('defaultLookups');

// ---------------------------------------------------------------------------
// table + for-select expose is_default
// ---------------------------------------------------------------------------

it('the admin table row exposes is_default', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['viewAny']));
    $modelClass::factory()->default()->create(['name' => 'Predefinita']);

    $rows = collect($this->postJson("/api/tables/{$resource}/rows", ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'));

    expect($rows->firstWhere('name', 'Predefinita')['is_default'])->toBeTrue();
})->with('defaultLookups');

it('for-select meta exposes is_default for every item', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $default = $modelClass::factory()->default()->create(['name' => 'Predefinita']);
    $ordinary = $modelClass::factory()->create(['name' => 'Ordinaria', 'is_default' => false]);

    $items = collect($this->getJson("/api/{$resource}/for-select")->assertOk()->json('items'))->keyBy('id');

    expect($items[$default->id]['meta']['is_default'])->toBeTrue()
        ->and($items[$ordinary->id]['meta']['is_default'])->toBeFalse();
})->with('defaultLookups');
