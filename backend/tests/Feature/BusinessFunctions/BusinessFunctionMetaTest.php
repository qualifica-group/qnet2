<?php

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

it('403 without business-functions.viewAny', function () {
    $actor = businessFunctionUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/business-functions')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order', function () {
    $actor = businessFunctionUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/business-functions')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'type', 'manager_id', 'parent_id', 'is_active', 'users', 'operational_sites', 'company_ids']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['type']['mandatory'])->toBeFalse()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['type']['type'])->toBe('select')
        ->and($fields['manager_id']['type'])->toBe('select')
        ->and($fields['parent_id']['type'])->toBe('select')
        ->and($fields['users']['type'])->toBe('multiselect')
        ->and($fields['operational_sites']['type'])->toBe('multiselect');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable when the actor may create', function () {
    $actor = businessFunctionUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/business-functions')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.type.editable', true);
});

it('permissions.fields are readonly when the actor may not create', function () {
    $actor = businessFunctionUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/business-functions')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true);
});

it('permissions.resource reflects the actor abilities including export/import', function () {
    $actor = businessFunctionUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/business-functions')
        ->assertOk()
        ->assertJsonPath('permissions.resource.view', false)
        ->assertJsonPath('permissions.resource.create', false)
        ->assertJsonPath('permissions.resource.export', true)
        ->assertJsonPath('permissions.resource.import', false);
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = businessFunctionUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/business-functions')
        ->assertOk()
        // create-context: delete is always false (no $model).
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
