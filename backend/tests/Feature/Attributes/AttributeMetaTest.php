<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('attributeUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function attributeUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("attributes.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attributes.{$ability}");
        }

        return $user;
    }
}

it('403 without attributes.viewAny', function () {
    $actor = attributeUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/attributes')->assertForbidden();
});

it('200: field catalogue matches the frozen contract, in order', function () {
    $actor = attributeUserWith(['viewAny', 'create']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/meta/attributes')->assertOk();

    $keys = collect($response->json('data.fields'))->pluck('key')->all();
    // REQUIREMENT CHANGED (spec 0429 D-7): the "Campo attuale" link closes the catalogue.
    expect($keys)->toBe(['code', 'name', 'type', 'description', 'help_text', 'placeholder', 'icon', 'config', 'relation_target', 'options', 'current_attribute_id']);

    $fields = collect($response->json('data.fields'))->keyBy('key');
    expect($fields['code']['mandatory'])->toBeTrue()
        ->and($fields['name']['mandatory'])->toBeTrue()
        ->and($fields['type']['type'])->toBe('select')
        ->and($fields['type']['mandatory'])->toBeTrue();
});

it('permissions.actions maps delete/export/import to the resource permissions', function () {
    $actor = attributeUserWith(['viewAny', 'export']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/meta/attributes')
        ->assertOk()
        ->assertJsonPath('permissions.actions.delete', false)
        ->assertJsonPath('permissions.actions.export', true)
        ->assertJsonPath('permissions.actions.import', false);
});
