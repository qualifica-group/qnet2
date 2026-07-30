<?php

use App\Models\Opportunity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-030 — permissions:sync creates all 8 opportunities.* permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 opportunities.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "opportunities.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the opportunities node only shows with opportunities.view (AC-080)', function () {
    Permission::findOrCreate('opportunities.view');

    // requirement changed (spec 0043, D-4): `opportunities` moved from a
    // standalone top-level entry into the "opportunities-group" collapsible
    // parent (alongside the new `opportunity-statuses`) — was `management`.
    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('opportunities');

    $withView = User::factory()->create();
    $withView->givePermissionTo('opportunities.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('opportunities');
});

// ---------------------------------------------------------------------------
// AC-032 — DB field-permission matrix (spec 0006, CHANGE-based enforcement)
// ---------------------------------------------------------------------------

it('update: estimated_value editable:false for the actor\'s role -> 422 on a CHANGED value, no write (AC-032)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }

    $role = Role::create(['name' => 'opportunity-value-locked']);
    $role->givePermissionTo(['opportunities.view', 'opportunities.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'estimated_value',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Opportunity::factory()->create(['estimated_value' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$target->id}", ['estimated_value' => 200])
        ->assertStatus(422)
        ->assertJsonValidationErrors('estimated_value');

    $this->assertDatabaseHas('opportunities', ['id' => $target->id, 'estimated_value' => 100]);
});

it('update: resubmitting the SAME (unchanged) value for a locked field is a no-op, not a 422 (AC-032)', function () {
    // A text field (mirrors LeadSecurityTest's `notes` precedent): a decimal-
    // cast field (e.g. estimated_value) is out of scope here — the shared
    // EnforcesFieldPermissions::normalize() does not yet reconcile a
    // decimal-cast "150.00" against a submitted numeric 150, a pre-existing
    // gap in that trait (also latent on Campaign/Project's own decimal
    // fields), not introduced by this module and out of scope for spec 0040.
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("opportunities.{$ability}");
    }

    $role = Role::create(['name' => 'opportunity-name-locked-noop']);
    $role->givePermissionTo(['opportunities.view', 'opportunities.update']);
    $role->fieldPermissions()->create([
        'resource' => 'opportunities',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Opportunity::factory()->create(['name' => 'Unchanged deal']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$target->id}", ['name' => 'Unchanged deal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Unchanged deal');
});

// ---------------------------------------------------------------------------
// AC-033 — a 403 (no base write ability) takes precedence over the field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no opportunities.update) takes precedence over a field-level 422', function () {
    $actor = User::factory()->create();
    Permission::findOrCreate('opportunities.update');
    $target = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunities/{$target->id}", ['estimated_value' => 999])->assertForbidden();
});
