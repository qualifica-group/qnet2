<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('registryMetaUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function registryMetaUserWith(array $abilities): User
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

it('403 without registries.viewAny', function () {
    $actor = registryMetaUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/registries')->assertForbidden();
});

it('200: field catalogue matches the frozen contract (14 registry + 12 personal_data), in order', function () {
    $actor = registryMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/registries')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe([
        'source_id', 'sector_ids', 'referent_ids', 'manager_slots',
        'supervisor_id', 'commercial_id', 'reporter_id',
        'vat_group', 'is_supplier', 'is_qualified_supplier',
        'agreement_status', 'agreement_notes', 'size_class', 'employee_count',
        'personal_data.type', 'personal_data.first_name',
        'personal_data.last_name', 'personal_data.company_name', 'personal_data.tax_code',
        'personal_data.vat_number', 'personal_data.sdi_code', 'personal_data.birth_date',
        'personal_data.birth_city_id', 'personal_data.gender',
        'personal_data.contacts', 'personal_data.addresses',
    ]);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['source_id']['type'])->toBe('select')
        ->and($fields['sector_ids']['type'])->toBe('multiselect')
        ->and($fields['referent_ids']['type'])->toBe('multiselect')
        ->and($fields['manager_slots']['type'])->toBe('multiselect')
        ->and($fields['is_supplier']['type'])->toBe('boolean')
        ->and($fields['is_supplier']['mandatory'])->toBeTrue()
        ->and($fields['is_qualified_supplier']['type'])->toBe('boolean')
        ->and($fields['employee_count']['type'])->toBe('number')
        ->and($fields['agreement_notes']['type'])->toBe('text')
        ->and($fields['personal_data.type']['mandatory'])->toBeTrue()
        ->and($fields['personal_data.first_name']['group'])->toBe('personal_data');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = registryMetaUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/registries')
        ->assertOk()
        ->assertJsonPath('permissions.fields.is_supplier.editable', true)
        ->assertJsonPath('permissions.fields.is_supplier.required', true);

    $fields = $this->getJson('/api/meta/registries')->json('permissions.fields');
    expect($fields['personal_data.first_name']['editable'])->toBeTrue();
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = registryMetaUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/registries')
        ->assertOk()
        ->assertJsonPath('permissions.fields.is_supplier.editable', false)
        ->assertJsonPath('permissions.fields.is_supplier.readonly', true);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = registryMetaUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/registries')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
