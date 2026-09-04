<?php

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
| Reorder on the four PURE lookups (spec 0101, D-4 rectified, AC-049)
|--------------------------------------------------------------------------
|
| Deliberately NOT a copy of the AC-047 suite, because the valid set is a
| different set: `task-statuses` reorders only the CUSTOM rows (the six
| system ones are pinned head/tail and must be absent), while these four
| tables have no `system_key` column at all, so the valid set is EVERY row.
| A test copied across without changing that would go green for the wrong
| reason — `assertReorderSetIsEveryRow()` below is the assertion that pins
| the difference down, and the last test in this file contrasts the two
| endpoints on the same payload shape.
|
| `sort_order` is server-managed with a step of 10 and resequences on every
| write, so nothing here asserts a literal value: only relative order, which
| is the actual contract.
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
 * The four PURE lookups. `task-statuses` is absent ON PURPOSE: its reorder
 * obeys AC-047, not AC-049.
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
dataset('pureLookupResources', [
    'task-types' => ['task-types', TaskType::class, 'task_types'],
    'task-categories' => ['task-categories', TaskCategory::class, 'task_categories'],
    'task-priorities' => ['task-priorities', TaskPriority::class, 'task_priorities'],
    'task-importances' => ['task-importances', TaskImportance::class, 'task_importances'],
]);

// ---------------------------------------------------------------------------
// AC-049 — what makes this NOT AC-047: there is no protected subset
// ---------------------------------------------------------------------------

it('AC-049: a pure lookup has no system rows at all, so the reorder set is EVERY row', function (string $resource, string $modelClass, string $table) {
    // The premise the rest of this file rests on. If a `system_key` column
    // ever appeared here, the valid-set rule would silently become the
    // task-statuses one and every assertion below would be testing the
    // wrong contract.
    expect(Schema::hasColumn($table, 'system_key'))->toBeFalse()
        ->and(defined($modelClass.'::SYSTEM_HEAD_KEYS'))->toBeFalse()
        ->and(defined($modelClass.'::SYSTEM_TAIL_KEYS'))->toBeFalse();

    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $rows = $modelClass::factory()->count(3)->create();

    // Every row is in play: the FULL id set is accepted, where the same
    // payload shape on task-statuses would be rejected for including the
    // six pinned rows.
    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => $rows->pluck('id')->all()])->assertOk();
})->with('pureLookupResources');

// ---------------------------------------------------------------------------
// AC-049 — the new order is real, and visible everywhere the rows are read
// ---------------------------------------------------------------------------

it('AC-049: reorder resequences the rows and the response comes back in the requested order', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $alfa = $modelClass::factory()->create(['name' => 'Alfa']);
    $beta = $modelClass::factory()->create(['name' => 'Beta']);
    $gamma = $modelClass::factory()->create(['name' => 'Gamma']);

    $response = $this->postJson("/api/{$resource}/reorder", [
        'ordered_ids' => [$gamma->id, $alfa->id, $beta->id],
    ])->assertOk();

    $response->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'message', 'data' => [['id', 'sort_order']]]);

    $orders = collect($response->json('data'))->keyBy('id');

    expect($orders[$gamma->id]['sort_order'])->toBeLessThan($orders[$alfa->id]['sort_order'])
        ->and($orders[$alfa->id]['sort_order'])->toBeLessThan($orders[$beta->id]['sort_order']);

    // Persisted, not just echoed back.
    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())
        ->toBe([$gamma->id, $alfa->id, $beta->id]);
})->with('pureLookupResources');

it('AC-049: the new order is reflected in the grid', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['viewAny', 'view', 'update']));
    $alfa = $modelClass::factory()->create(['name' => 'Alfa']);
    $beta = $modelClass::factory()->create(['name' => 'Beta']);

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$beta->id, $alfa->id]])->assertOk();

    // The grid's default sort is `sort_order asc`, so the reorder is what
    // decides the listing — not the creation date, which was the whole
    // reason D-4 was rectified.
    $names = collect($this->postJson("/api/tables/{$resource}/rows", ['startRow' => 0, 'endRow' => 25])
        ->assertOk()->json('items'))->pluck('name')->all();

    expect($names)->toBe(['Beta', 'Alfa']);
})->with('pureLookupResources');

it('AC-049: the new order is reflected in for-select', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $alfa = $modelClass::factory()->create(['name' => 'Alfa']);
    $beta = $modelClass::factory()->create(['name' => 'Beta']);

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$beta->id, $alfa->id]])->assertOk();

    $ids = collect($this->getJson("/api/{$resource}/for-select")->assertOk()->json('items'))->pluck('id')->all();

    expect($ids)->toBe([$beta->id, $alfa->id]);
})->with('pureLookupResources');

// ---------------------------------------------------------------------------
// AC-049 — the four rejected payloads
// ---------------------------------------------------------------------------

it('AC-049: 422 when ordered_ids repeats an id, and the order is untouched', function (string $resource, string $modelClass, string $table) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $first = $modelClass::factory()->create();
    $second = $modelClass::factory()->create();
    $originalOrder = $modelClass::query()->orderBy('sort_order')->pluck('id')->all();

    // ReorderStatusesRequest declares `distinct` on `ordered_ids.*`, so the
    // shape-level rule fires BEFORE LookupOrderManager's own duplicate
    // guard: the refusal arrives as a field error, not as the manager's
    // sentence. Asserting the field is what pins the real behaviour.
    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$first->id, $first->id, $second->id]])
        ->assertStatus(422)->assertJsonValidationErrors('ordered_ids.0');

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe($originalOrder);
    $this->assertDatabaseHas($table, ['id' => $first->id]);
})->with('pureLookupResources');

it('AC-049: 422 when ordered_ids contains an id that does not exist, and the order is untouched', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $rows = $modelClass::factory()->count(2)->create();
    $originalOrder = $modelClass::query()->orderBy('sort_order')->pluck('id')->all();
    $unknownId = (int) $modelClass::query()->max('id') + 1000;

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [...$rows->pluck('id')->all(), $unknownId]])
        ->assertStatus(422)
        ->assertJsonPath('message', 'ordered_ids must contain exactly every row of this configurator (none unknown, none missing).');

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe($originalOrder);
})->with('pureLookupResources');

it('AC-049: 422 when ordered_ids OMITS a row, and the order is untouched', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $first = $modelClass::factory()->create();
    $second = $modelClass::factory()->create();
    $third = $modelClass::factory()->create();
    $originalOrder = $modelClass::query()->orderBy('sort_order')->pluck('id')->all();

    // Every row is reorderable here, so a missing one is a missing one —
    // there is no pinned subset that would legitimately be absent.
    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$third->id, $first->id]])
        ->assertStatus(422)
        ->assertJsonPath('message', 'ordered_ids must contain exactly every row of this configurator (none unknown, none missing).');

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe($originalOrder)
        ->and($second->fresh())->not->toBeNull();
})->with('pureLookupResources');

it('AC-049: 422 when ordered_ids has the right COUNT but swaps a row for an unknown id', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $first = $modelClass::factory()->create();
    $second = $modelClass::factory()->create();
    $originalOrder = $modelClass::query()->orderBy('sort_order')->pluck('id')->all();
    $unknownId = (int) $modelClass::query()->max('id') + 1000;

    // Same cardinality as the table: a guard that only counted would pass.
    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$first->id, $unknownId]])
        ->assertStatus(422);

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe($originalOrder)
        ->and($second->fresh())->not->toBeNull();
})->with('pureLookupResources');

it('AC-049: an empty ordered_ids on a non-empty table is 422', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['update']));
    $modelClass::factory()->count(2)->create();

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => []])->assertStatus(422);
})->with('pureLookupResources');

// ---------------------------------------------------------------------------
// AC-049 — authorization
// ---------------------------------------------------------------------------

it('AC-049: reorder requires authentication (401)', function (string $resource, string $modelClass) {
    $row = $modelClass::factory()->create();

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$row->id]])->assertUnauthorized();
})->with('pureLookupResources');

it('AC-049: 403 without the resource .update permission, and the order is untouched', function (string $resource, string $modelClass) {
    // Gated on `.update`, not on `.create` or a bespoke ability: reorder is
    // a write to existing rows.
    Sanctum::actingAs(taskConfigActorWith($resource, ['viewAny', 'view', 'create', 'delete']));
    $first = $modelClass::factory()->create();
    $second = $modelClass::factory()->create();
    $originalOrder = $modelClass::query()->orderBy('sort_order')->pluck('id')->all();

    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$second->id, $first->id]])
        ->assertForbidden();

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe($originalOrder);
})->with('pureLookupResources');

// ---------------------------------------------------------------------------
// AC-049 — reorder is the ONLY way in: sort_order stays prohibited
// ---------------------------------------------------------------------------

it('AC-049: sort_order stays prohibited in store and update, so reorder is the only writer', function (string $resource, string $modelClass) {
    Sanctum::actingAs(taskConfigActorWith($resource, ['create', 'update']));

    $this->postJson("/api/{$resource}", taskConfigStorePayload($resource, ['sort_order' => 5]))
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');

    $row = $modelClass::factory()->create();
    $originalOrder = $row->sort_order;

    $this->patchJson("/api/{$resource}/{$row->id}", ['sort_order' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');

    expect($row->fresh()->sort_order)->toBe($originalOrder);

    // ...and the dedicated endpoint DOES move it, so the column is reachable
    // exactly once (the gap D-4 was rectified to close).
    $other = $modelClass::factory()->create();
    $this->postJson("/api/{$resource}/reorder", ['ordered_ids' => [$other->id, $row->id]])->assertOk();

    expect($modelClass::query()->orderBy('sort_order')->pluck('id')->all())->toBe([$other->id, $row->id]);
})->with('pureLookupResources');

it('AC-049: sort_order stays prohibited on task-statuses too, which keeps its own reorder', function () {
    Sanctum::actingAs(taskConfigActorWith('task-statuses', ['create', 'update']));

    $this->postJson('/api/task-statuses', taskConfigStorePayload('task-statuses', ['sort_order' => 5]))
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');

    $custom = TaskStatus::factory()->create();

    $this->patchJson("/api/task-statuses/{$custom->id}", ['sort_order' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('sort_order');
});

// ---------------------------------------------------------------------------
// The contrast that keeps this suite honest
// ---------------------------------------------------------------------------

it('AC-049 vs AC-047: the same "every row" payload is accepted here and rejected on task-statuses', function (string $resource, string $modelClass) {
    // BOTH actors are built before the first Sanctum::actingAs(): that call
    // switches the default auth driver to `sanctum`, so a Permission created
    // after it is stamped with the `sanctum` guard while User resolves its
    // abilities against `web` — and givePermissionTo() would then not find
    // the second resource's abilities at all.
    $lookupActor = taskConfigActorWith($resource, ['update']);
    $statusActor = taskConfigActorWith('task-statuses', ['update']);

    // Pure lookup: every row is the valid set -> 200.
    Sanctum::actingAs($lookupActor);
    $modelClass::factory()->count(2)->create();

    $this->postJson("/api/{$resource}/reorder", [
        'ordered_ids' => $modelClass::query()->orderBy('id')->pluck('id')->all(),
    ])->assertOk();

    // Statuses: every row includes the three pinned protected rows -> 422.
    Sanctum::actingAs($statusActor);
    TaskStatus::factory()->count(2)->create();

    expect(TaskStatus::query()->whereNotNull('system_key')->count())->toBe(3);

    $this->postJson('/api/task-statuses/reorder', [
        'ordered_ids' => TaskStatus::query()->orderBy('id')->pluck('id')->all(),
    ])->assertStatus(422);
})->with('pureLookupResources');
