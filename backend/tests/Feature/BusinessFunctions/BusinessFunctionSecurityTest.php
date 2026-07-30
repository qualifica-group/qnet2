<?php

use App\Models\BusinessFunction;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('businessFunctionUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function businessFunctionUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-011 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = businessFunctionUserWith([]); // no business-functions.update at all
    $target = BusinessFunction::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-011 — DB field-permission matrix (spec 0006) parity with users/roles:
// an editable:false row rejects a CHANGED value with a field-keyed 422, no write.
// ---------------------------------------------------------------------------

it('update: manager_id editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("business-functions.{$ability}");
    }

    $role = Role::create(['name' => 'manager-locked']);
    $role->givePermissionTo(['business-functions.view', 'business-functions.update']);
    $role->fieldPermissions()->create([
        'resource' => 'business-functions',
        'field' => 'manager_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $originalManager = User::factory()->create();
    $newManager = User::factory()->create();
    $target = BusinessFunction::factory()->create(['manager_id' => $originalManager->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['manager_id' => $newManager->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('manager_id');

    $this->assertDatabaseHas('business_functions', ['id' => $target->id, 'manager_id' => $originalManager->id]);
});

it('update: submitting the SAME (unchanged) value for a locked field is a no-op, not a 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("business-functions.{$ability}");
    }

    $role = Role::create(['name' => 'manager-locked-noop']);
    $role->givePermissionTo(['business-functions.view', 'business-functions.update']);
    $role->fieldPermissions()->create([
        'resource' => 'business-functions',
        'field' => 'manager_id',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $manager = User::factory()->create();
    $target = BusinessFunction::factory()->create(['manager_id' => $manager->id, 'name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", [
        'name' => 'After',
        'manager_id' => $manager->id,
    ])->assertOk()->assertJsonPath('data.name', 'After');
});

it('update: 200 and persists when submitting only editable fields', function () {
    $actor = businessFunctionUserWith(['update']);
    $target = BusinessFunction::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/business-functions/{$target->id}", ['name' => 'After'])->assertOk();

    $this->assertDatabaseHas('business_functions', ['id' => $target->id, 'name' => 'After']);
});

// ---------------------------------------------------------------------------
// AC-013 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 business-functions.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "business-functions.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the business-functions node only shows with business-functions.view', function () {
    Permission::findOrCreate('business-functions.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('business-functions');

    $withView = User::factory()->create();
    $withView->givePermissionTo('business-functions.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('business-functions');
});
