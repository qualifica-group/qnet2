<?php

use App\Models\Role;
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
// AC-015 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 sectors.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "sectors.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-015 — navigation node gated by sectors.view
// ---------------------------------------------------------------------------

it('navigation: the sectors node only shows with sectors.view', function () {
    Permission::findOrCreate('sectors.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('sectors');

    $withView = User::factory()->create();
    $withView->givePermissionTo('sectors.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('sectors');
});

// ---------------------------------------------------------------------------
// AC-016 — field permissions
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = sectorUserWith([]);
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: EnforcesFieldPermissions rejects a write to parent_id when the role locks it, no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("sectors.{$ability}");
    }

    $role = Role::create(['name' => 'sector-locked']);
    $role->givePermissionTo(['sectors.view', 'sectors.update']);
    $role->fieldPermissions()->create([
        'resource' => 'sectors',
        'field' => 'parent_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $newParent = Sector::factory()->create();
    $target = Sector::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$target->id}", ['parent_id' => $newParent->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('parent_id');

    expect($target->fresh()->parent_id)->toBeNull();
});

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("sectors.{$ability}");
    }

    $role = Role::create(['name' => 'sector-name-locked']);
    $role->givePermissionTo(['sectors.view', 'sectors.update']);
    $role->fieldPermissions()->create([
        'resource' => 'sectors',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Sector::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/sectors/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('sectors', ['id' => $target->id, 'name' => 'Changed']);
});
