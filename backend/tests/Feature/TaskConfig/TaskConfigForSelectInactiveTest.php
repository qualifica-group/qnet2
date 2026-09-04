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
| `include_inactive` on the five for-select endpoints (T-03c)
|--------------------------------------------------------------------------
|
| Regression cover for a real bug, not a new feature in the abstract: the
| reorder sheet fed itself from the for-select, which filters
| `is_active = true` by default, so as soon as ONE row was deactivated the
| sheet submitted an incomplete `ordered_ids` and every drag came back 422.
|
| The bug is only reproduced by a test that goes through BOTH endpoints —
| take the set exactly as the sheet takes it, then submit it — which is what
| the second half of this file does. Asserting only "the flag returns one
| more item" would leave the actual defect uncovered.
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

/**
 * All FIVE configurators: `include_inactive` exists on every for-select
 * (T-03c), statuses included.
 *
 * @return array<string, array{0: string, 1: string}>
 */
dataset('allTaskConfigurators', [
    'task-statuses' => ['task-statuses', TaskStatus::class],
    'task-types' => ['task-types', TaskType::class],
    'task-categories' => ['task-categories', TaskCategory::class],
    'task-priorities' => ['task-priorities', TaskPriority::class],
    'task-importances' => ['task-importances', TaskImportance::class],
]);

// ---------------------------------------------------------------------------
// the parameter itself
// ---------------------------------------------------------------------------

it('for-select hides an inactive row by default and shows it with include_inactive=1', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $active = $modelClass::factory()->create(['name' => 'Attiva', 'is_active' => true]);
    $inactive = $modelClass::factory()->create(['name' => 'Disattivata', 'is_active' => false]);

    $default = collect($this->getJson("/api/{$resource}/for-select")->assertOk()->json('items'))->pluck('id');
    expect($default)->toContain($active->id)->not->toContain($inactive->id);

    $lifted = collect($this->getJson("/api/{$resource}/for-select?include_inactive=1")->assertOk()->json('items'))->pluck('id');
    expect($lifted)->toContain($active->id)->toContain($inactive->id);
})->with('allTaskConfigurators');

it('for-select treats include_inactive=0 as the ordinary behaviour', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $inactive = $modelClass::factory()->create(['name' => 'Disattivata', 'is_active' => false]);

    $ids = collect($this->getJson("/api/{$resource}/for-select?include_inactive=0")->assertOk()->json('items'))->pluck('id');

    expect($ids)->not->toContain($inactive->id);
})->with('allTaskConfigurators');

it('for-select rejects a non-boolean include_inactive', function (string $resource) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));

    $this->getJson("/api/{$resource}/for-select?include_inactive=maybe")
        ->assertStatus(422)->assertJsonValidationErrors('include_inactive');
})->with('allTaskConfigurators');

it('include_inactive does not lift the ordering or the search', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, []));
    $late = $modelClass::factory()->create(['name' => 'Zeta cercabile', 'sort_order' => 900, 'is_active' => false]);
    $early = $modelClass::factory()->create(['name' => 'Alfa cercabile', 'sort_order' => 901, 'is_active' => false]);
    $modelClass::factory()->create(['name' => 'Fuori ricerca', 'is_active' => false]);

    $ids = collect($this->getJson("/api/{$resource}/for-select?include_inactive=1&search=cercabile")->assertOk()->json('items'))
        ->pluck('id')->all();

    // Still sort_order-first, not alphabetical, and still search-filtered:
    // the flag lifts one predicate, not the whole query.
    expect($ids)->toBe([$late->id, $early->id]);
})->with('allTaskConfigurators');

// ---------------------------------------------------------------------------
// the bug T-03c actually closed: the reorder set must be complete
// ---------------------------------------------------------------------------

/**
 * The four PURE lookups, whose reorder set is EVERY row (D-4). Statuses have
 * their own set rule and are exercised separately below.
 *
 * @return array<string, array{0: string, 1: string}>
 */
dataset('pureLookupsForInactiveReorder', [
    'task-types' => ['task-types', TaskType::class],
    'task-categories' => ['task-categories', TaskCategory::class],
    'task-priorities' => ['task-priorities', TaskPriority::class],
    'task-importances' => ['task-importances', TaskImportance::class],
]);

it('the reorder set taken from the DEFAULT for-select is rejected once a row is deactivated', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $modelClass::factory()->create(['name' => 'Prima', 'is_active' => true]);
    $modelClass::factory()->create(['name' => 'Disattivata', 'is_active' => false]);

    // Exactly what the sheet used to submit: the filtered list.
    $filteredIds = collect($this->getJson("/api/{$resource}/for-select")->assertOk()->json('items'))->pluck('id')->all();

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => $filteredIds])
        ->assertStatus(422)
        ->assertJsonPath('message', 'ordered_ids must contain exactly every row of this configurator (none unknown, none missing).');
})->with('pureLookupsForInactiveReorder');

it('the reorder set taken WITH include_inactive is accepted, and reorders the inactive row too', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $active = $modelClass::factory()->create(['name' => 'Prima', 'is_active' => true]);
    $inactive = $modelClass::factory()->create(['name' => 'Disattivata', 'is_active' => false]);

    $fullIds = collect($this->getJson("/api/{$resource}/for-select?include_inactive=1")->assertOk()->json('items'))
        ->pluck('id')->all();

    expect($fullIds)->toHaveCount(2);

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$inactive->id, $active->id]])->assertOk();

    // A deactivated row is still an ordered row: it keeps its place in the
    // sequence rather than being pushed out of it.
    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$inactive->id, $active->id]);
})->with('pureLookupsForInactiveReorder');

it('task-statuses: the reorder set is the CUSTOM rows including the deactivated ones', function () {
    Sanctum::actingAs(taskConfigActorWith('task-statuses', ['update']));
    $activeCustom = TaskStatus::factory()->create(['name' => 'Custom attiva', 'is_active' => true]);
    $inactiveCustom = TaskStatus::factory()->create(['name' => 'Custom disattivata', 'is_active' => false]);

    // The default for-select drops the deactivated custom AND carries the six
    // system rows, so it is wrong in both directions at once.
    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$activeCustom->id]])->assertStatus(422);

    $this->postJson('/api/task-statuses/reorder', ['ordered_ids' => [$inactiveCustom->id, $activeCustom->id]])
        ->assertOk();

    $customOrder = TaskStatus::query()->whereNull('system_key')->orderBy('sort_order')->pluck('id')->all();
    expect($customOrder)->toBe([$inactiveCustom->id, $activeCustom->id]);
});
