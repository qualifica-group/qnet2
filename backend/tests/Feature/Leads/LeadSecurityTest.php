<?php

use App\Models\Lead;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-030 — permissions:sync creates all 7 leads.* permissions, unlisted anywhere
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 leads.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "leads.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the leads node only shows with leads.view', function () {
    Permission::findOrCreate('leads.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('leads');

    $withView = User::factory()->create();
    $withView->givePermissionTo('leads.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('leads');
});

// ---------------------------------------------------------------------------
// AC-032 — DB field-permission matrix (spec 0006, CHANGE-based enforcement)
// ---------------------------------------------------------------------------

it('update: notes editable:false for the actor\'s role -> 422 on a CHANGED value, no write (AC-032)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $role = Role::create(['name' => 'lead-notes-locked']);
    $role->givePermissionTo(['leads.view', 'leads.update']);
    $role->fieldPermissions()->create([
        'resource' => 'leads',
        'field' => 'notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Lead::factory()->create(['notes' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$target->id}", ['notes' => 'Changed'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notes');

    $this->assertDatabaseHas('leads', ['id' => $target->id, 'notes' => 'Original']);
});

it('update: resubmitting the SAME (unchanged) value for a locked field is a no-op, not a 422 (AC-032)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("leads.{$ability}");
    }

    $role = Role::create(['name' => 'lead-notes-locked-noop']);
    $role->givePermissionTo(['leads.view', 'leads.update']);
    $role->fieldPermissions()->create([
        'resource' => 'leads',
        'field' => 'notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Lead::factory()->create(['notes' => 'Unchanged']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$target->id}", ['notes' => 'Unchanged'])
        ->assertOk()
        ->assertJsonPath('data.notes', 'Unchanged');
});

// ---------------------------------------------------------------------------
// AC-033 — a 403 (no base write ability) takes precedence over the field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no leads.update) takes precedence over a field-level 422', function () {
    $actor = User::factory()->create();
    Permission::findOrCreate('leads.update');
    $target = Lead::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/leads/{$target->id}", ['notes' => 'Nope'])->assertForbidden();
});
