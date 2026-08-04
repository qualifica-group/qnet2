<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('changeRequestColumnActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function changeRequestColumnActorWith(array $abilities): User
    {
        foreach (['viewAny', 'update', 'updateSource'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        return $user;
    }
}

// Server support for AC-045/AC-046 (spec 0078, D-2/D-7): the `source`
// column's `change_request` flag on GET /api/tables/request-management/columns.

it('`source` carries `change_request` and stays editable for an actor without updateSource', function () {
    $actor = changeRequestColumnActorWith(['viewAny', 'update']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['source']['editable'])->toBeTrue()
        ->and($columns['source']['change_request'])->toBe([
            'resource' => 'request-management',
            'field' => 'source_id',
        ]);
});

it('`source` carries NO `change_request` and stays editable for an actor WITH updateSource', function () {
    $actor = changeRequestColumnActorWith(['viewAny', 'update', 'updateSource']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    expect($columns['source']['editable'])->toBeTrue()
        ->and($columns['source'])->not->toHaveKey('change_request');
});

it('no column carries `change_request` for an actor without `request-management.update` at all', function () {
    $actor = changeRequestColumnActorWith(['viewAny']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'));

    foreach ($columns as $column) {
        expect($column)->not->toHaveKey('change_request');
    }

    expect($columns->firstWhere('id', 'source')['editable'])->toBeFalse();
});

// AC-005 regression guard: a domain with NO protected fields in config
// (`users`) must never see a `change_request` key, byte-identical to before
// this spec.

it('no column of the `users` domain carries a `change_request` key (AC-005 regression guard)', function () {
    Permission::findOrCreate('users.viewAny');
    Permission::findOrCreate('users.update');
    $actor = User::factory()->create();
    $actor->givePermissionTo(['users.viewAny', 'users.update']);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/users/columns')->assertOk()->json('data.columns'));

    foreach ($columns as $column) {
        expect($column)->not->toHaveKey('change_request');
    }
});
