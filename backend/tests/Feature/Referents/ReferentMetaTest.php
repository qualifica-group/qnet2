<?php

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

it('403 without referents.viewAny', function () {
    $actor = referentUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/referents')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order', function () {
    $actor = referentUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/referents')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe([
        // Spec 0090, D-2: `user_id` joins as the 4th native key.
        'referent_type_id', 'contact_scope', 'notes', 'user_id',
        'personal_data.type', 'personal_data.first_name',
        'personal_data.last_name', 'personal_data.company_name', 'personal_data.tax_code',
        'personal_data.vat_number', 'personal_data.sdi_code', 'personal_data.birth_date',
        'personal_data.birth_city_id', 'personal_data.residence_city_id', 'personal_data.gender',
        'personal_data.contacts', 'personal_data.addresses',
    ]);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['referent_type_id']['mandatory'])->toBeFalse()
        ->and($fields['referent_type_id']['type'])->toBe('select')
        ->and($fields['contact_scope']['mandatory'])->toBeTrue()
        ->and($fields['contact_scope']['type'])->toBe('select')
        ->and($fields['notes']['mandatory'])->toBeFalse()
        ->and($fields['notes']['type'])->toBe('text')
        ->and($fields['user_id']['mandatory'])->toBeFalse()
        ->and($fields['user_id']['type'])->toBe('select')
        ->and($fields['personal_data.first_name']['group'])->toBe('personal_data');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = referentUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/referents')
        ->assertOk()
        ->assertJsonPath('permissions.fields.contact_scope.editable', true)
        ->assertJsonPath('permissions.fields.contact_scope.required', true);

    $fields = $this->getJson('/api/meta/referents')->json('permissions.fields');
    expect($fields['personal_data.first_name']['editable'])->toBeTrue();
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = referentUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/referents')
        ->assertOk()
        ->assertJsonPath('permissions.fields.contact_scope.editable', false)
        ->assertJsonPath('permissions.fields.contact_scope.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = referentUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/referents')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
