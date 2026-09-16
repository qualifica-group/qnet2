<?php

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
// AC-008 — GET /api/meta/operational-sites
// ---------------------------------------------------------------------------

it('403 without operational-sites.viewAny', function () {
    $actor = userWithSiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/operational-sites')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order, with mandatory flags', function () {
    $actor = userWithSiteAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/operational-sites')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['alias', 'country_id', 'state_id', 'province_id', 'city_id', 'line1', 'postal_code', 'is_active']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['alias']['mandatory'])->toBeFalse()
        ->and($fields['alias']['type'])->toBe('text')
        ->and($fields['country_id']['mandatory'])->toBeFalse()
        ->and($fields['state_id']['mandatory'])->toBeFalse()
        ->and($fields['province_id']['mandatory'])->toBeFalse()
        ->and($fields['city_id']['mandatory'])->toBeTrue()
        ->and($fields['line1']['mandatory'])->toBeTrue()
        ->and($fields['postal_code']['mandatory'])->toBeFalse()
        ->and($fields['city_id']['type'])->toBe('select')
        ->and($fields['line1']['type'])->toBe('text')
        ->and($fields['postal_code']['type'])->toBe('text')
        ->and($fields['is_active']['mandatory'])->toBeFalse()
        ->and($fields['is_active']['type'])->toBe('boolean');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = userWithSiteAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/operational-sites')
        ->assertOk()
        ->assertJsonPath('permissions.fields.line1.editable', true)
        ->assertJsonPath('permissions.fields.line1.required', true)
        ->assertJsonPath('permissions.fields.city_id.editable', true)
        ->assertJsonPath('permissions.fields.city_id.required', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = userWithSiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/operational-sites')
        ->assertOk()
        ->assertJsonPath('permissions.fields.line1.editable', false)
        ->assertJsonPath('permissions.fields.line1.readonly', true);
});

it('permissions.resource reflects the actor abilities including export/import', function () {
    $actor = userWithSiteAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/operational-sites')
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', false)
        ->assertJsonPath('permissions.resource.create', false)
        ->assertJsonPath('permissions.resource.export', true)
        ->assertJsonPath('permissions.resource.import', false);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = userWithSiteAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/operational-sites')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
