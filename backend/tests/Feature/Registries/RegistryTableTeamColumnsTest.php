<?php

use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
 * The team columns of the Anagrafiche grid: Commerciale, Supervisore,
 * Segnalatore (own-FK relations) and Operatori (`managers`, the
 * registry_user pivot) — rows shape, set filter, sort and /values.
 */

function registryTeamColumnsViewer(): User
{
    Permission::findOrCreate('registries.viewAny');

    return User::factory()->create()->givePermissionTo('registries.viewAny');
}

/**
 * @param  array<string, mixed>  $payload
 * @return array<int, array<string, mixed>>
 */
function registryTeamColumnsRows(array $payload = []): array
{
    return test()->postJson('/api/tables/registries/rows', ['startRow' => 0, 'endRow' => 25, ...$payload])
        ->assertOk()
        ->json('items');
}

it('declares commercial/supervisor/reporter as sortable set columns and managers as a non-sortable set column', function () {
    Sanctum::actingAs(registryTeamColumnsViewer());

    $columns = collect($this->getJson('/api/tables/registries/columns')->assertOk()->json('data.columns'))->keyBy('id');

    foreach (['commercial', 'supervisor', 'reporter'] as $id) {
        expect($columns[$id])->toMatchArray(['sortable' => true, 'filterable' => true, 'filterType' => 'set', 'visible' => true]);
    }

    expect($columns['managers'])->toMatchArray(['sortable' => false, 'filterable' => true, 'filterType' => 'set', 'visible' => true]);
});

it('rows expose the team relations and the managers in slot order', function () {
    $actor = registryTeamColumnsViewer();
    $commercial = Referent::factory()->create(['name' => 'Carla Commerciale']);
    $reporter = Referent::factory()->create(['name' => 'Sergio Segnalatore']);
    $supervisor = User::factory()->create(['name' => 'Sara Supervisore']);
    $second = User::factory()->create(['name' => 'Bruno']);
    $first = User::factory()->create(['name' => 'Anna']);
    $registry = Registry::factory()->create([
        'name' => 'Team Registry',
        'commercial_id' => $commercial->id,
        'reporter_id' => $reporter->id,
        'supervisor_id' => $supervisor->id,
    ]);
    $registry->managers()->attach([$second->id => ['position' => 2], $first->id => ['position' => 1]]);
    Registry::factory()->create(['name' => 'Empty Team']);
    Sanctum::actingAs($actor);

    $rows = collect(registryTeamColumnsRows())->keyBy('name');

    expect($rows['Team Registry']['commercial'])->toBe(['id' => $commercial->id, 'name' => 'Carla Commerciale'])
        ->and($rows['Team Registry']['reporter'])->toBe(['id' => $reporter->id, 'name' => 'Sergio Segnalatore'])
        ->and($rows['Team Registry']['supervisor'])->toMatchArray(['id' => $supervisor->id, 'name' => 'Sara Supervisore'])
        ->and($rows['Team Registry']['supervisor'])->toHaveKey('avatar_url')
        ->and(array_column($rows['Team Registry']['managers'], 'name'))->toBe(['Anna', 'Bruno'])
        ->and($rows['Empty Team']['commercial'])->toBeNull()
        ->and($rows['Empty Team']['supervisor'])->toBeNull()
        ->and($rows['Empty Team']['reporter'])->toBeNull()
        ->and($rows['Empty Team']['managers'])->toBe([]);
});

it('resolves the team columns with a bounded query count (no N+1)', function () {
    $actor = registryTeamColumnsViewer();

    foreach (range(1, 5) as $i) {
        $registry = Registry::factory()->create([
            'commercial_id' => Referent::factory()->create()->id,
            'reporter_id' => Referent::factory()->create()->id,
            'supervisor_id' => User::factory()->create()->id,
        ]);
        $registry->managers()->attach(User::factory()->create()->id, ['position' => 1]);
    }

    Sanctum::actingAs($actor);

    DB::enableQueryLog();
    expect(registryTeamColumnsRows())->toHaveCount(5);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Fixed per page: one query per eager-loaded relation, never per row.
    expect($queryCount)->toBeLessThan(16);
});

it('filters by a team relation name and by the blank entry', function () {
    $actor = registryTeamColumnsViewer();
    $carla = Referent::factory()->create(['name' => 'Carla']);
    $mario = Referent::factory()->create(['name' => 'Mario']);
    Registry::factory()->create(['name' => 'With Carla', 'commercial_id' => $carla->id]);
    Registry::factory()->create(['name' => 'With Mario', 'commercial_id' => $mario->id]);
    Registry::factory()->create(['name' => 'Without', 'commercial_id' => null]);
    Sanctum::actingAs($actor);

    $filtered = registryTeamColumnsRows(['filterModel' => ['commercial' => ['filterType' => 'set', 'values' => ['Carla', null]]]]);

    expect(collect($filtered)->pluck('name')->sort()->values()->all())->toBe(['With Carla', 'Without']);
});

it('filters by an operator (managers pivot) name', function () {
    $actor = registryTeamColumnsViewer();
    $anna = User::factory()->create(['name' => 'Anna']);
    $bruno = User::factory()->create(['name' => 'Bruno']);
    Registry::factory()->create(['name' => 'Anna Registry'])->managers()->attach($anna->id, ['position' => 2]);
    Registry::factory()->create(['name' => 'Bruno Registry'])->managers()->attach($bruno->id, ['position' => 1]);
    Sanctum::actingAs($actor);

    $filtered = registryTeamColumnsRows(['filterModel' => ['managers' => ['filterType' => 'set', 'values' => ['Anna']]]]);

    expect(collect($filtered)->pluck('name')->all())->toBe(['Anna Registry']);
});

it('sorts by the supervisor name via a correlated subquery', function () {
    $actor = registryTeamColumnsViewer();
    Registry::factory()->create(['name' => 'Z-registry', 'supervisor_id' => User::factory()->create(['name' => 'Zoe'])->id]);
    Registry::factory()->create(['name' => 'A-registry', 'supervisor_id' => User::factory()->create(['name' => 'Alba'])->id]);
    Sanctum::actingAs($actor);

    $names = collect(registryTeamColumnsRows(['sortModel' => [['colId' => 'supervisor', 'sort' => 'desc']]]))->pluck('name')->all();

    expect(array_search('Z-registry', $names, true))->toBeLessThan(array_search('A-registry', $names, true));
});

it('resolves distinct reporter and operator names via /values, blank entry first', function () {
    $actor = registryTeamColumnsViewer();
    $reporter = Referent::factory()->create(['name' => 'Sergio']);
    $anna = User::factory()->create(['name' => 'Anna']);
    Registry::factory()->create(['reporter_id' => $reporter->id])->managers()->attach($anna->id, ['position' => 1]);
    Registry::factory()->create(['reporter_id' => null]);
    Sanctum::actingAs($actor);

    $reporters = $this->postJson('/api/tables/registries/values', ['columnId' => 'reporter'])->assertOk()->json('data.values');
    $managers = $this->postJson('/api/tables/registries/values', ['columnId' => 'managers'])->assertOk()->json('data.values');

    expect($reporters)->toBe([null, 'Sergio'])
        ->and($managers)->toBe([null, 'Anna']);
});
