<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('documentLayoutUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function documentLayoutUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("document-layouts.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("document-layouts.{$ability}");
        }

        return $user;
    }
}

it('403 without document-layouts.viewAny', function () {
    $actor = documentLayoutUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/document-layouts')->assertForbidden();
});

it('200: field catalogue is [name, code, description, module, is_active, is_default, config], in this frozen order (AC-060)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/document-layouts')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'code', 'description', 'module', 'is_active', 'is_default', 'config']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['code']['mandatory'])->toBeTrue()
        ->and($fields['code']['type'])->toBe('text')
        ->and($fields['description']['mandatory'])->toBeFalse()
        ->and($fields['description']['type'])->toBe('textarea')
        ->and($fields['module']['mandatory'])->toBeTrue()
        ->and($fields['module']['type'])->toBe('select')
        ->and($fields['is_active']['mandatory'])->toBeFalse()
        ->and($fields['is_active']['type'])->toBe('boolean')
        ->and($fields['is_default']['mandatory'])->toBeFalse()
        ->and($fields['is_default']['type'])->toBe('boolean')
        ->and($fields['config']['mandatory'])->toBeTrue()
        ->and($fields['config']['type'])->toBe('json');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: permissions.fields.code/module.editable are true only in create context (AC-060)', function () {
    $actor = documentLayoutUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/document-layouts')
        ->assertOk()
        ->assertJsonPath('permissions.fields.code.editable', true)
        ->assertJsonPath('permissions.fields.code.required', true)
        ->assertJsonPath('permissions.fields.module.editable', true)
        ->assertJsonPath('permissions.fields.module.required', true);

    // GET /meta always resolves model=null (create-context skeleton); the
    // readonly-on-existing-record branch is exercised by GET show and PATCH
    // (DocumentLayoutCrudTest AC-020/021).
});

it('permissions.actions maps delete/export/import/view_activity to the resource permissions', function () {
    $actor = documentLayoutUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/document-layouts')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false)
        ->assertJsonPath('permissions.actions.view_activity', false);
});
