<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * A non super-admin actor granted exactly the given `roles.*` abilities.
 * Mirrors the helper in RoleUserMembershipTest (guarded for redeclare safety).
 *
 * @param  array<int, string>  $abilities
 */
it('exposes the users_count column and counts assigned users per role', function () {
    $actor = actorWithRoleAbilities(['viewAny']);

    $editor = Role::create(['name' => 'editor']);
    Role::create(['name' => 'viewer']); // no members

    User::factory()->count(2)->create()->each->assignRole($editor);

    Sanctum::actingAs($actor);

    $rows = collect($this->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk()->json('items'))->keyBy('name');

    expect($rows['editor'])->toHaveKey('users_count')
        ->and($rows['editor']['users_count'])->toBe(2)
        ->and($rows['viewer']['users_count'])->toBe(0);
});

it('sorts roles by users_count', function () {
    $actor = actorWithRoleAbilities(['viewAny']);

    $many = Role::create(['name' => 'many']);
    $few = Role::create(['name' => 'few']);

    User::factory()->count(3)->create()->each->assignRole($many);
    User::factory()->create()->assignRole($few);

    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'users_count', 'sort' => 'desc']],
    ])->assertOk()->json('items.*.name');

    // 'many' (3 members) must precede 'few' (1 member) under desc sort.
    expect(array_search('many', $names, true))
        ->toBeLessThan(array_search('few', $names, true));
});

it('filters roles by an exact users_count', function () {
    $actor = actorWithRoleAbilities(['viewAny']);

    $two = Role::create(['name' => 'two']);
    $one = Role::create(['name' => 'one']);
    Role::create(['name' => 'zero']); // no members

    User::factory()->count(2)->create()->each->assignRole($two);
    User::factory()->create()->assignRole($one);

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'users_count' => ['filterType' => 'number', 'type' => 'equals', 'filter' => 2],
        ],
    ])->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.name'))->toBe('two')
        ->and($response->json('items.0.users_count'))->toBe(2);
});

it('filters roles by a users_count range (inRange)', function () {
    $actor = actorWithRoleAbilities(['viewAny']);

    $three = Role::create(['name' => 'three']);
    $two = Role::create(['name' => 'two']);
    $one = Role::create(['name' => 'one']);

    User::factory()->count(3)->create()->each->assignRole($three);
    User::factory()->count(2)->create()->each->assignRole($two);
    User::factory()->create()->assignRole($one);

    Sanctum::actingAs($actor);

    $names = collect($this->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => [
            'users_count' => ['filterType' => 'number', 'type' => 'inRange', 'filter' => 2, 'filterTo' => 3],
        ],
    ])->assertOk()->json('items.*.name'));

    // Only roles with 2..3 members; 'one' (1 member) is excluded.
    expect($names->all())->toEqualCanonicalizing(['three', 'two']);
});
