<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-statuses.{$ability}");
        }

        return $user;
    }
}

it('403 without reward-statuses.viewAny', function () {
    $actor = rewardStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-statuses')->assertForbidden();
});

it('200: field catalogue is [name, description, color, is_active], in this frozen order (AC-014)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/reward-statuses')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'description', 'color', 'group', 'is_active']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['description']['mandatory'])->toBeFalse()
        ->and($fields['description']['type'])->toBe('textarea')
        ->and($fields['color']['mandatory'])->toBeTrue()
        ->and($fields['color']['type'])->toBe('color')
        ->and($fields['group']['mandatory'])->toBeTrue()
        ->and($fields['group']['type'])->toBe('select')
        ->and($fields['is_active']['mandatory'])->toBeFalse()
        ->and($fields['is_active']['type'])->toBe('boolean');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable+required on mandatory fields (AC-014)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.color.editable', true)
        ->assertJsonPath('permissions.fields.color.required', true)
        ->assertJsonPath('permissions.fields.description.editable', true)
        ->assertJsonPath('permissions.fields.is_active.editable', true);
});

it('permissions.fields are readonly when the actor may not create (AC-014)', function () {
    $actor = rewardStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true)
        ->assertJsonPath('permissions.fields.color.editable', false)
        ->assertJsonPath('permissions.fields.color.readonly', true);
});

it('permissions.actions maps delete/export/import/view_activity to the resource permissions (AC-014)', function () {
    $actor = rewardStatusUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-statuses')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false)
        ->assertJsonPath('permissions.actions.view_activity', false);
});
