<?php

use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("registries.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("registries.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// Spec 0207 — "Note generali" of the anagrafica
// ---------------------------------------------------------------------------

it('AC-001: create persists general_notes and the detail returns them', function () {
    Sanctum::actingAs(registryUserWith(['create']));

    $this->postJson('/api/registries', [
        'is_supplier' => false,
        'general_notes' => 'Call only in the morning',
        'personal_data' => minimalRegistryProfilePayload(),
    ])->assertCreated()
        ->assertJsonPath('data.general_notes', 'Call only in the morning');

    expect(Registry::first()->general_notes)->toBe('Call only in the morning');
});

it('AC-002: PATCH with only general_notes writes them and leaves the other fields untouched', function () {
    Sanctum::actingAs(registryUserWith(['update']));
    $registry = Registry::factory()->create(['agreement_notes' => 'Agreement', 'vat_group' => 'VG-1']);

    $this->patchJson("/api/registries/{$registry->id}", ['general_notes' => 'New note'])
        ->assertOk()
        ->assertJsonPath('data.general_notes', 'New note')
        ->assertJsonPath('data.agreement_notes', 'Agreement')
        ->assertJsonPath('data.vat_group', 'VG-1');
});

it('AC-002: PATCH general_notes=null clears them', function () {
    Sanctum::actingAs(registryUserWith(['update']));
    $registry = Registry::factory()->create(['general_notes' => 'Old note']);

    $this->patchJson("/api/registries/{$registry->id}", ['general_notes' => null])
        ->assertOk()
        ->assertJsonPath('data.general_notes', null);

    expect($registry->fresh()->general_notes)->toBeNull();
});

it('AC-003: 422 when general_notes exceed 5000 characters', function () {
    Sanctum::actingAs(registryUserWith(['update']));
    $registry = Registry::factory()->create();

    $this->patchJson("/api/registries/{$registry->id}", ['general_notes' => str_repeat('a', 5001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('general_notes');
});

it('AC-004: 403 on PATCH general_notes without registries.update', function () {
    Sanctum::actingAs(registryUserWith(['view']));
    $registry = Registry::factory()->create();

    $this->patchJson("/api/registries/{$registry->id}", ['general_notes' => 'Nope'])
        ->assertForbidden();

    expect($registry->fresh()->general_notes)->toBeNull();
});

it('AC-004: the field permission is editable with update, read-only without', function () {
    $registry = Registry::factory()->withPersonalData()->create();

    Sanctum::actingAs(registryUserWith(['view', 'update']));
    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.general_notes.visible', true)
        ->assertJsonPath('permissions.fields.general_notes.editable', true);

    Sanctum::actingAs(registryUserWith(['view']));
    $this->getJson("/api/registries/{$registry->id}")
        ->assertOk()
        ->assertJsonPath('permissions.fields.general_notes.visible', true)
        ->assertJsonPath('permissions.fields.general_notes.editable', false);
});
