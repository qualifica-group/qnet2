<?php

use App\Models\Attribute;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-019 parity — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = attributeUserWith([]);
    $target = Attribute::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("attributes.{$ability}");
    }

    $role = Role::create(['name' => 'attribute-locked']);
    $role->givePermissionTo(['attributes.view', 'attributes.update']);
    $role->fieldPermissions()->create([
        'resource' => 'attributes',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Attribute::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/attributes/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');
});

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 attributes.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "attributes.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by attributes.view
// ---------------------------------------------------------------------------

it('navigation: the attributes node only shows with attributes.view', function () {
    Permission::findOrCreate('attributes.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('attributes');

    $withView = User::factory()->create();
    $withView->givePermissionTo('attributes.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('attributes');
});
