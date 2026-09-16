<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * The Set Filter's blank entry on DERIVED columns (the ones a TableDefinition
 * resolves itself, where no `WHERE column IS NULL` can express "empty cell").
 * AG Grid renders a `null` among a column's values as "(Vuoti)" and sends it
 * straight back inside the filter model, so both directions are covered here:
 * the value list offers it, and picking it returns exactly the rows whose cell
 * is empty. The REAL columns go through the generic engine and are covered by
 * ProductCategoryTableTest.
 */
if (! function_exists('blankFilterActor')) {
    function blankFilterActor(): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $actor = User::factory()->create(['name' => 'Actor']);
        $actor->givePermissionTo('users.viewAny');

        return $actor;
    }
}

it('offers the blank entry on the derived roles column and filters the role-less users', function () {
    $actor = blankFilterActor();
    $role = Role::findOrCreate('editor');
    $withRole = User::factory()->create(['name' => 'With role']);
    $withRole->assignRole($role);
    User::factory()->create(['name' => 'Without role']);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/users/values', ['columnId' => 'roles'])
        ->assertOk()
        ->json('data.values');

    expect($values[0])->toBeNull();

    $rows = $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['roles' => ['filterType' => 'set', 'values' => [null]]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('name')->all())->toEqualCanonicalizing(['Actor', 'Without role']);
});

it('combines the blank entry with a picked role instead of replacing it', function () {
    $actor = blankFilterActor();
    $role = Role::findOrCreate('editor');
    $withRole = User::factory()->create(['name' => 'With role']);
    $withRole->assignRole($role);
    User::factory()->create(['name' => 'Without role']);
    Sanctum::actingAs($actor);

    $rows = $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => ['roles' => ['filterType' => 'set', 'values' => ['editor', null]]],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('name')->all())
        ->toEqualCanonicalizing(['Actor', 'With role', 'Without role']);
});

it('offers the blank entry on the computed primary_contact column and filters the card-less users', function () {
    $actor = blankFilterActor();
    $reachable = User::factory()->create(['name' => 'Reachable']);
    $card = PersonalData::factory()->individual()->for($reachable, 'personable')->create();
    Contact::factory()->primary()->for($card, 'contactable')->create(['type' => 'email', 'value' => 'ada@example.com']);
    User::factory()->create(['name' => 'No contact']);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/users/values', ['columnId' => 'primary_contact'])
        ->assertOk()
        ->json('data.values');

    expect($values)->toBe([null, 'ada@example.com']);

    // The grid sends the Set sub-model inside the `multi` envelope here.
    $rows = $this->postJson('/api/tables/users/rows', [
        'startRow' => 0, 'endRow' => 25,
        'filterModel' => [
            'primary_contact' => [
                'filterType' => 'multi',
                'filterModels' => [['filterType' => 'set', 'values' => [null]], null],
            ],
        ],
    ])->assertOk()->json('items');

    expect(collect($rows)->pluck('name')->all())->toEqualCanonicalizing(['Actor', 'No contact']);
});

it('drops the blank entry while the user is searching the value list', function () {
    $actor = blankFilterActor();
    $reachable = User::factory()->create(['name' => 'Reachable']);
    $card = PersonalData::factory()->individual()->for($reachable, 'personable')->create();
    Contact::factory()->primary()->for($card, 'contactable')->create(['type' => 'email', 'value' => 'ada@example.com']);
    User::factory()->create(['name' => 'No contact']);
    Sanctum::actingAs($actor);

    $values = $this->postJson('/api/tables/users/values', ['columnId' => 'primary_contact', 'search' => 'ada'])
        ->assertOk()
        ->json('data.values');

    expect($values)->toBe(['ada@example.com']);
});
