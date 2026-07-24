<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

it('403 without company-sites.viewAny', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/company-sites')->assertForbidden();
});

it('200: field catalogue is grouped profile/personal_data/settings/banks, mandatory on name', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/company-sites')
        ->assertOk()
        ->assertJsonPath('success', true);

    $fields = collect($response->json('data.fields'))->keyBy('key');

    expect($fields['name']['group'])->toBe('profile')
        ->and($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['logo']['group'])->toBe('profile')
        ->and($fields['personal_data.company_name']['group'])->toBe('personal_data')
        ->and($fields['personal_data.vat_number']['group'])->toBe('personal_data')
        ->and($fields['personal_data.contacts']['group'])->toBe('personal_data')
        ->and($fields['personal_data.contacts']['type'])->toBe('collection')
        ->and($fields['personal_data.addresses']['group'])->toBe('personal_data')
        ->and($fields['personal_data.addresses']['type'])->toBe('collection')
        ->and($fields['company_id']['group'])->toBe('settings')
        ->and($fields['banks']['group'])->toBe('banks')
        ->and($fields['banks']['type'])->toBe('collection');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: profile/settings fields are editable when the actor may create', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/company-sites')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.company_id.editable', true)
        ->assertJsonPath('permissions.fields.banks.editable', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = userWithCompanySiteAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/company-sites')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.resource reflects the actor abilities including export/import', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/company-sites')
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', false)
        ->assertJsonPath('permissions.resource.create', false)
        ->assertJsonPath('permissions.resource.export', true)
        ->assertJsonPath('permissions.resource.import', false);
});

it('permissions.actions maps upload_logo/delete_logo/set_default to the update ability', function () {
    $actor = userWithCompanySiteAbilities(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/company-sites')
        ->assertOk()
        // Create-context (model=null): logo/set-default actions require an
        // existing model, so they are false even though `update` is granted.
        ->assertJsonPath('permissions.actions.upload_logo', false)
        ->assertJsonPath('permissions.actions.delete_logo', false)
        ->assertJsonPath('permissions.actions.set_default', false);
});
