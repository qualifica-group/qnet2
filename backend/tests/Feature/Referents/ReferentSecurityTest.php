<?php

use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-013 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = referentUserWith([]);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['notes' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-013 — DB field-permission matrix (spec 0006) parity with users/roles: an
// editable:false row rejects a CHANGED value with a field-keyed 422, no write.
// ---------------------------------------------------------------------------

it('update: notes editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $role = Role::create(['name' => 'referent-notes-locked']);
    $role->givePermissionTo(['referents.view', 'referents.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referents',
        'field' => 'notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Referent::factory()->create(['notes' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['notes' => 'Changed'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notes');

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'notes' => 'Original']);
});

it('update: submitting the SAME (unchanged) value for a locked field is a no-op, not a 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $role = Role::create(['name' => 'referent-notes-locked-noop']);
    $role->givePermissionTo(['referents.view', 'referents.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referents',
        'field' => 'notes',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Referent::factory()->create(['notes' => 'Same', 'contact_scope' => 'internal']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['notes' => 'Same', 'contact_scope' => 'external'])
        ->assertOk()
        ->assertJsonPath('data.contact_scope', 'external');
});

// ---------------------------------------------------------------------------
// AC-013 — DB field-permission matrix on a personal_data.* key (parity with
// UsersAuthorization's own personal_data.* matrix behavior).
// ---------------------------------------------------------------------------

it('update: personal_data.tax_code editable:false for the actor\'s role -> 422, no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("referents.{$ability}");
    }

    $role = Role::create(['name' => 'referent-taxcode-locked']);
    $role->givePermissionTo(['referents.view', 'referents.update']);
    $role->fieldPermissions()->create([
        'resource' => 'referents',
        'field' => 'personal_data.tax_code',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Referent::factory()->create();
    PersonalData::factory()->for($target, 'personable')->create(['tax_code' => 'ORIGINAL01']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'tax_code' => 'CHANGED02',
        ],
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.tax_code');

    $this->assertDatabaseHas('personal_data', ['personable_id' => $target->id, 'tax_code' => 'ORIGINAL01']);
});

// ---------------------------------------------------------------------------
// AC-002/AC-017 — permissions:sync + navigation
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 referents.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "referents.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the referents node only shows with referents.view', function () {
    Permission::findOrCreate('referents.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('referents');

    $withView = User::factory()->create();
    $withView->givePermissionTo('referents.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('referents');
});
