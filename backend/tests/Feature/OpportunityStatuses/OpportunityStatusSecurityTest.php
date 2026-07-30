<?php

use App\Models\OpportunityStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunity-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// A base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = opportunityStatusUserWith([]);
    $target = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// DB field-permission matrix (spec 0006/0008): `name` is mandatory, so its
// ceiling can never be narrowed by a DB role_field_permissions row.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("opportunity-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'opportunity-status-locked']);
    $role->givePermissionTo(['opportunity-statuses.view', 'opportunity-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunity-statuses',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = OpportunityStatus::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// AC-010 — a restrictive DB row on the NON-mandatory `color` field IS
// enforced.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on `color` rejects a real change with a 422 (AC-010)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("opportunity-statuses.{$ability}");
    }

    $role = Role::create(['name' => 'opportunity-status-color-locked']);
    $role->givePermissionTo(['opportunity-statuses.view', 'opportunity-statuses.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunity-statuses',
        'field' => 'color',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = OpportunityStatus::factory()->create(['name' => 'Original', 'color' => 'slate']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['color' => 'green'])
        ->assertStatus(422)->assertJsonValidationErrors('color');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id, 'color' => 'slate']);
});

// ---------------------------------------------------------------------------
// AC-002 — permissions:sync creates the 8 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 opportunity-statuses.* permissions (AC-002)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "opportunity-statuses.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-017 — navigation node gated by opportunity-statuses.view
// ---------------------------------------------------------------------------

it('navigation: the opportunity-statuses node only shows with opportunity-statuses.view (AC-017)', function () {
    Permission::findOrCreate('opportunity-statuses.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('opportunity-statuses');

    $withView = User::factory()->create();
    $withView->givePermissionTo('opportunity-statuses.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('opportunity-statuses');
});
