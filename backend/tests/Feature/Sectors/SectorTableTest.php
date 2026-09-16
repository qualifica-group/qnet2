<?php

use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-012 — columns config
// ---------------------------------------------------------------------------

it('returns the 4 columns in order with the declared flags, 403 without viewAny', function () {
    $actor = sectorUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/sectors/columns')->assertForbidden();

    $actor = sectorUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/sectors/columns')->assertOk()->json('data');

    expect($data['resource'])->toBe('sectors')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'parent', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['parent']['filterType'])->toBe('set')
        ->and($columns['parent']['sortable'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-013 — rows shape (no N+1: parent eager-loaded)
// ---------------------------------------------------------------------------

it('rows expose id/name/parent{id,name}|null/created_at + per-row actions', function () {
    $actor = sectorUserWith(['viewAny', 'view', 'update', 'delete']);
    $root = Sector::factory()->create(['name' => 'Root']);
    $child = Sector::factory()->childOf($root)->create(['name' => 'Child']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sectors/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $rootRow = collect($response->json('items'))->firstWhere('name', 'Root');
    expect($rootRow['parent'])->toBeNull();

    $childRow = collect($response->json('items'))->firstWhere('name', 'Child');
    expect($childRow['parent'])->toBe(['id' => $root->id, 'name' => 'Root'])
        ->and($childRow['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('rows: no N+1 on the parent relation', function () {
    $actor = sectorUserWith(['viewAny']);
    $root = Sector::factory()->create();
    Sector::factory()->childOf($root)->count(5)->create();
    Sanctum::actingAs($actor);

    Sector::preventLazyLoading();

    $this->postJson('/api/tables/sectors/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    Sector::preventLazyLoading(false);
});

// ---------------------------------------------------------------------------
// AC-013 — derived `parent` filter/sort/distinct
// ---------------------------------------------------------------------------

it('filter: parent set filter narrows the rows via whereHas', function () {
    $actor = sectorUserWith(['viewAny']);
    $rootA = Sector::factory()->create(['name' => 'Energy']);
    $rootB = Sector::factory()->create(['name' => 'Mobility']);
    Sector::factory()->childOf($rootA)->create(['name' => 'Solar']);
    Sector::factory()->childOf($rootB)->create(['name' => 'Transit']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sectors/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['parent' => ['filterType' => 'set', 'values' => ['Energy']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name')->all();
    expect($names)->toBe(['Solar']);
});

it('sort: rows ordered by the derived parent name', function () {
    $actor = sectorUserWith(['viewAny']);
    $rootA = Sector::factory()->create(['name' => 'Zebra Root']);
    $rootB = Sector::factory()->create(['name' => 'Alpha Root']);
    Sector::factory()->childOf($rootA)->create(['name' => 'FromZebra']);
    Sector::factory()->childOf($rootB)->create(['name' => 'FromAlpha']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sectors/rows', [
        'startRow' => 0, 'endRow' => 25,
        'sortModel' => [['colId' => 'parent', 'sort' => 'asc']],
    ])->assertOk();

    $names = collect($response->json('items'))
        ->whereIn('name', ['FromZebra', 'FromAlpha'])
        ->pluck('name')->values()->all();
    expect($names)->toBe(['FromAlpha', 'FromZebra']);
});

it('values: parent → distinct parent names, columnId outside the allow-list → 422', function () {
    $actor = sectorUserWith(['viewAny']);
    $rootA = Sector::factory()->create(['name' => 'Energy']);
    $rootB = Sector::factory()->create(['name' => 'Mobility']);
    Sector::factory()->childOf($rootA)->create();
    Sector::factory()->childOf($rootB)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/sectors/values', ['columnId' => 'parent'])->assertOk();
    // `null` is the blank entry ("(Vuoti)"): the root sectors have no parent.
    expect($response->json('data.values'))->toEqualCanonicalizing([null, 'Energy', 'Mobility']);

    $this->postJson('/api/tables/sectors/values', ['columnId' => 'not_a_column'])
        ->assertStatus(422)->assertJsonValidationErrors('columnId');
});

// ---------------------------------------------------------------------------
// AC-014 — bulk-delete respects the restrictive-delete guard
// ---------------------------------------------------------------------------

it('bulk-delete: a sector with children is guarded, not force-deleted', function () {
    $actor = sectorUserWith(['viewAny', 'delete']);
    $parent = Sector::factory()->create();
    Sector::factory()->childOf($parent)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/sectors/bulk-delete', ['ids' => [$parent->id]])->assertOk();

    $this->assertDatabaseHas('sectors', ['id' => $parent->id]);
});
