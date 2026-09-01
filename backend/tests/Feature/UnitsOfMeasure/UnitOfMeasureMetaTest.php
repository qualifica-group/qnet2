<?php

use App\Models\Role;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('unitOfMeasureUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function unitOfMeasureUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("units-of-measure.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("units-of-measure.{$ability}");
        }

        return $user;
    }
}

it('403 without units-of-measure.viewAny', function () {
    $actor = unitOfMeasureUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/units-of-measure')->assertForbidden();
});

it('200: field catalogue is [code, name, symbol, description], in this frozen order (AC-023)', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/units-of-measure')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['code', 'name', 'symbol', 'description']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['code']['mandatory'])->toBeFalse()
        ->and($fields['code']['type'])->toBe('text')
        ->and($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['symbol']['mandatory'])->toBeTrue()
        ->and($fields['symbol']['type'])->toBe('text')
        ->and($fields['description']['mandatory'])->toBeFalse()
        ->and($fields['description']['type'])->toBe('textarea');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: permissions.fields.code.editable is true only in create context (AC-023)', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/units-of-measure')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.code.required', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = unitOfMeasureUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/units-of-measure')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = unitOfMeasureUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/units-of-measure')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});

// ---------------------------------------------------------------------------
// AC-024 — a restrictive DB row narrows a non-mandatory field
// ---------------------------------------------------------------------------

it('a restrictive DB row on the non-mandatory `description` field makes it readonly, and 422 if modified (AC-024)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("units-of-measure.{$ability}");
    }

    $role = Role::create(['name' => 'unit-of-measure-locked']);
    $role->givePermissionTo(['units-of-measure.view', 'units-of-measure.update']);
    $role->fieldPermissions()->create([
        'resource' => 'units-of-measure',
        'field' => 'description',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = UnitOfMeasure::factory()->create(['description' => 'Original']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/units-of-measure/{$target->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.description.editable', false);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['description' => 'Changed'])
        ->assertStatus(422)->assertJsonValidationErrors('description');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'description' => 'Original']);
});

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("units-of-measure.{$ability}");
    }

    $role = Role::create(['name' => 'unit-of-measure-name-locked']);
    $role->givePermissionTo(['units-of-measure.view', 'units-of-measure.update']);
    $role->fieldPermissions()->create([
        'resource' => 'units-of-measure',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = UnitOfMeasure::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/units-of-measure/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('units_of_measure', ['id' => $target->id, 'name' => 'Changed']);
});
