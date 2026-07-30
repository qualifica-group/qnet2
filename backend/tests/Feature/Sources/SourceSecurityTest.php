<?php

use App\Models\Role;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('sourceUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function sourceUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("sources.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("sources.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-003 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = sourceUserWith([]);
    $target = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sources/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// DB field-permission matrix (spec 0006/0008): `name` is the resource's only
// (and mandatory) field — a mandatory field's ceiling can never be narrowed
// by a DB role_field_permissions row (spec 0008), so a restrictive row is
// simply ignored and the write still succeeds.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("sources.{$ability}");
    }

    $role = Role::create(['name' => 'source-locked']);
    $role->givePermissionTo(['sources.view', 'sources.update']);
    $role->fieldPermissions()->create([
        'resource' => 'sources',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Source::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sources/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('sources', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-003 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 sources.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "sources.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-008 — navigation node gated by sources.view
// ---------------------------------------------------------------------------

it('navigation: the sources node only shows with sources.view', function () {
    Permission::findOrCreate('sources.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('sources');

    $withView = User::factory()->create();
    $withView->givePermissionTo('sources.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('sources');
});
