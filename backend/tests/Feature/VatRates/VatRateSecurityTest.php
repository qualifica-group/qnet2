<?php

use App\Models\Role;
use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('vatRateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function vatRateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("vat-rates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("vat-rates.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = vatRateUserWith([]);
    $target = VatRate::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/vat-rates/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// DB field-permission matrix (spec 0006/0008): `name`/`rate` are mandatory —
// a mandatory field's ceiling can never be narrowed by a DB
// role_field_permissions row (spec 0008), so a restrictive row is simply
// ignored and the write still succeeds.
// ---------------------------------------------------------------------------

it('update: a restrictive DB row on the mandatory `name` field is ignored (mandatory bypass), write succeeds', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("vat-rates.{$ability}");
    }

    $role = Role::create(['name' => 'vat-rate-locked']);
    $role->givePermissionTo(['vat-rates.view', 'vat-rates.update']);
    $role->fieldPermissions()->create([
        'resource' => 'vat-rates',
        'field' => 'name',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = VatRate::factory()->create(['name' => 'Original']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/vat-rates/{$target->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed');

    $this->assertDatabaseHas('vat_rates', ['id' => $target->id, 'name' => 'Changed']);
});

// ---------------------------------------------------------------------------
// permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 vat-rates.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "vat-rates.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// navigation node gated by vat-rates.view
// ---------------------------------------------------------------------------

it('navigation: the vat-rates node only shows with vat-rates.view', function () {
    Permission::findOrCreate('vat-rates.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->not->toContain('vat-rates');

    $withView = User::factory()->create();
    $withView->givePermissionTo('vat-rates.view');
    Sanctum::actingAs($withView);
    expect(navigationNodeKeys($this->getJson('/api/navigation')->json('data')))
        ->toContain('vat-rates');
});
