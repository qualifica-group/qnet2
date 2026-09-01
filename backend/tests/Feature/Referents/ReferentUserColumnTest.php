<?php

use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-006 — the `user` derived column: row value, filter, sort, distinct
// (mirrors BusinessFunctions' own `manager` column exactly).
// ---------------------------------------------------------------------------

it('rows expose the linked user\'s name, null when unlinked', function () {
    $actor = referentUserWith(['viewAny']);
    $linkedUser = User::factory()->create(['name' => 'Ada Lovelace']);
    Referent::factory()->forUser($linkedUser)->create(['name' => 'Linked Referent']);
    Referent::factory()->create(['name' => 'Unlinked Referent']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $items = collect($response->json('items'));

    expect($items->firstWhere('name', 'Linked Referent')['user'])->toBe('Ada Lovelace')
        ->and($items->firstWhere('name', 'Unlinked Referent')['user'])->toBeNull();
});

it('filters rows by the derived user set filter (whereHas by name)', function () {
    $actor = referentUserWith(['viewAny']);
    $ada = User::factory()->create(['name' => 'Ada Lovelace']);
    $grace = User::factory()->create(['name' => 'Grace Hopper']);
    Referent::factory()->forUser($ada)->create(['name' => 'Referent A']);
    Referent::factory()->forUser($grace)->create(['name' => 'Referent B']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['user' => ['filterType' => 'set', 'values' => ['Ada Lovelace']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Referent A']);
});

it('sorts rows by the derived user name via a correlated subquery', function () {
    $actor = referentUserWith(['viewAny']);
    $zed = User::factory()->create(['name' => 'Zed User']);
    $amy = User::factory()->create(['name' => 'Amy User']);
    Referent::factory()->forUser($zed)->create(['name' => 'Z-referent']);
    Referent::factory()->forUser($amy)->create(['name' => 'A-referent']);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/referents/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'user', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-referent', $names, true))->toBeLessThan(array_search('Z-referent', $names, true));
});

it('resolves distinct linked-user names via /values', function () {
    $actor = referentUserWith(['viewAny']);
    $linkedUser = User::factory()->create(['name' => 'Ada Lovelace']);
    Referent::factory()->forUser($linkedUser)->create();
    Referent::factory()->create(); // unlinked
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/referents/values', ['columnId' => 'user'])->assertOk();

    expect($response->json('data.values'))->toBe(['Ada Lovelace']);
});
