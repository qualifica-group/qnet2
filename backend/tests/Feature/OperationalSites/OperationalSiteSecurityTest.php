<?php

use App\Models\OperationalSite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithSiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithSiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("operational-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("operational-sites.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-012 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = userWithSiteAbilities([]); // no operational-sites.update at all
    $target = OperationalSite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['line1' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-012 — DB field-permission matrix (spec 0006): an editable:false row
// rejects a CHANGED value with a field-keyed 422, no write.
// ---------------------------------------------------------------------------

it('update: postal_code editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("operational-sites.{$ability}");
    }

    $role = Role::create(['name' => 'site-postal-locked']);
    $role->givePermissionTo(['operational-sites.view', 'operational-sites.update']);
    $role->fieldPermissions()->create([
        'resource' => 'operational-sites',
        'field' => 'postal_code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = OperationalSite::factory()->create();
    $target->addresses()->create([
        'line1' => 'Via Test',
        'postal_code' => '00100',
        'is_primary' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['postal_code' => '99999'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('postal_code');

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'operational_site',
        'addressable_id' => $target->id,
        'postal_code' => '00100',
    ]);
});

it('update: submitting the SAME (unchanged) value for a locked field is a no-op, not a 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("operational-sites.{$ability}");
    }

    $role = Role::create(['name' => 'site-postal-locked-noop']);
    $role->givePermissionTo(['operational-sites.view', 'operational-sites.update']);
    $role->fieldPermissions()->create([
        'resource' => 'operational-sites',
        'field' => 'postal_code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = OperationalSite::factory()->create();
    $target->addresses()->create([
        'line1' => 'Before',
        'postal_code' => '00100',
        'is_primary' => true,
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", [
        'line1' => 'After',
        'postal_code' => '00100',
    ])->assertOk()->assertJsonPath('data.line1', 'After');
});

it('update: 200 and persists when submitting only editable fields', function () {
    $actor = userWithSiteAbilities(['update']);
    $target = OperationalSite::factory()->create();
    $target->addresses()->create(['line1' => 'Before', 'is_primary' => true]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/operational-sites/{$target->id}", ['line1' => 'After'])->assertOk();

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'operational_site',
        'addressable_id' => $target->id,
        'line1' => 'After',
    ]);
});

// ---------------------------------------------------------------------------
// AC-014 — permissions:sync creates all 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 operational-sites.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "operational-sites.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the operational-sites node only shows with operational-sites.view', function () {
    Permission::findOrCreate('operational-sites.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('operational-sites');

    $withView = User::factory()->create();
    $withView->givePermissionTo('operational-sites.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('operational-sites');
});
