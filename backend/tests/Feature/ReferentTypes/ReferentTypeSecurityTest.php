<?php

use App\Models\ReferentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referent-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referent-types.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-002 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = referentTypeUserWith([]);
    $target = ReferentType::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referent-types/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// DB field-permission matrix (spec 0006/0008): `name` is the resource's only
// (and mandatory) field — a mandatory field's ceiling can never be narrowed
// by a DB role_field_permissions row (spec 0008), so a restrictive row is
// simply ignored and the write still succeeds.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referent-types.{$ability}");
    }

    $role = Role::create(['name' => 'referent-type-locked']);
    $role->givePermissionTo(['referent-types.view', 'referent-types.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referent-types',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = ReferentType::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referent-types/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('referent_types', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-002 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 referent-types.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "referent-types.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-002 — navigation node gated by referent-types.view
// ---------------------------------------------------------------------------

it('navigation: the referent-types node only shows with referent-types.view', function () {
    Permission::findOrCreate('referent-types.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('referent-types');

    $withView = User::factory()->create();
    $withView->givePermissionTo('referent-types.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('referent-types');
});
