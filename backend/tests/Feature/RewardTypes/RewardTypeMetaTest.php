<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('rewardTypeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function rewardTypeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("reward-types.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("reward-types.{$ability}");
        }

        return $user;
    }
}

it('403 without reward-types.viewAny', function () {
    $actor = rewardTypeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-types')->assertForbidden();
});

it('200: field catalogue is [name, color], both mandatory, in this order (AC-013)', function () {
    $actor = rewardTypeUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/reward-types')
        ->assertOk()
        ->assertJsonPath('success', true);

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    expect($keys)->toBe(['name', 'color']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['name']['type'])->toBe('text')
        ->and($fields['color']['mandatory'])->toBeTrue()
        ->and($fields['color']['type'])->toBe('color');

    foreach ($response->json('permissions.fields') as $field) {
        expect($field)->toHaveKeys(['visible', 'hidden', 'editable', 'readonly', 'required', 'disabled']);
    }
});

it('200: create-context permissions.fields are editable+required when the actor may create (AC-013)', function () {
    $actor = rewardTypeUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-types')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', true)
        ->assertJsonPath('permissions.fields.name.required', true)
        ->assertJsonPath('permissions.fields.color.editable', true)
        ->assertJsonPath('permissions.fields.color.required', true);
});

it('permissions.fields are readonly when the actor may not create (AC-013)', function () {
    $actor = rewardTypeUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-types')
        ->assertOk()
        ->assertJsonPath('permissions.fields.name.editable', false)
        ->assertJsonPath('permissions.fields.name.readonly', true)
        ->assertJsonPath('permissions.fields.color.editable', false)
        ->assertJsonPath('permissions.fields.color.readonly', true);
});

it('permissions.actions maps delete/export/import/view_activity to the resource permissions', function () {
    $actor = rewardTypeUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/reward-types')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false)
        ->assertJsonPath('permissions.actions.view_activity', false);
});
