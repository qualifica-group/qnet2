<?php

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanyAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = userWithCompanyAbilities([]); // no companies.update at all
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['denomination' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-007 — DB field-permission matrix (spec 0006): an editable:false row
// rejects a CHANGED value with a field-keyed 422, no write.
// ---------------------------------------------------------------------------

it('update: vat_number editable:false for the actor\'s role -> 422 "field not editable", no write', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }

    $role = Role::create(['name' => 'company-vat-locked']);
    $role->givePermissionTo(['companies.view', 'companies.update']);
    $role->fieldPermissions()->create([
        'resource' => 'companies',
        'field' => 'vat_number',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Company::factory()->create(['vat_number' => 'IT111']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['vat_number' => 'IT222'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('vat_number');

    $this->assertDatabaseHas('companies', ['id' => $target->id, 'vat_number' => 'IT111']);
});

it('update: submitting the SAME (unchanged) value for a locked field is a no-op, not a 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("companies.{$ability}");
    }

    $role = Role::create(['name' => 'company-vat-locked-noop']);
    $role->givePermissionTo(['companies.view', 'companies.update']);
    $role->fieldPermissions()->create([
        'resource' => 'companies',
        'field' => 'vat_number',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Company::factory()->create(['vat_number' => 'IT11111111115', 'denomination' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", [
        'denomination' => 'After',
        'vat_number' => 'IT11111111115',
    ])->assertOk()->assertJsonPath('data.denomination', 'After');
});

it('update: 200 and persists when submitting only editable fields', function () {
    $actor = userWithCompanyAbilities(['update']);
    $target = Company::factory()->create(['denomination' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['denomination' => 'After'])->assertOk();

    $this->assertDatabaseHas('companies', ['id' => $target->id, 'denomination' => 'After']);
});

// ---------------------------------------------------------------------------
// AC-012 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 companies.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "companies.{$ability}")->exists())->toBeTrue();
    }
});

it('navigation: the companies node only shows with companies.view', function () {
    Permission::findOrCreate('companies.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('companies');

    $withView = User::factory()->create();
    $withView->givePermissionTo('companies.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('companies');
});
