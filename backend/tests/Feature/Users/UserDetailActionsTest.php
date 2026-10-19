<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * `permissions.actions` of GET /api/users/{user}: the record actions the user
 * detail header offers (delete, "Accedi come").
 */
function userDetailActor(bool $mayImpersonate): User
{
    $actor = userWithUserAbilities(['view', 'update', 'delete']);
    if ($mayImpersonate) {
        Permission::findOrCreate('users.impersonate');
        $actor->givePermissionTo('users.impersonate');
    }

    return $actor;
}

function userDetailActions(User $actor, User $target): array
{
    Sanctum::actingAs($actor);

    return test()->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.actions');
}

it('offers delete and impersonate on an active colleague when the actor holds both permissions', function () {
    $actions = userDetailActions(userDetailActor(true), User::factory()->create(['is_active' => true]));

    expect($actions['delete'])->toBeTrue()
        ->and($actions['impersonate'])->toBeTrue();
});

it('never offers impersonate without users.impersonate', function () {
    $actions = userDetailActions(userDetailActor(false), User::factory()->create(['is_active' => true]));

    expect($actions['impersonate'])->toBeFalse();
});

it('never offers impersonate on an inactive target', function () {
    $actions = userDetailActions(userDetailActor(true), User::factory()->create(['is_active' => false]));

    expect($actions['impersonate'])->toBeFalse();
});

it('offers neither delete nor impersonate on the actor own record', function () {
    $actor = userDetailActor(true);

    $actions = userDetailActions($actor, $actor);

    expect($actions['delete'])->toBeFalse()
        ->and($actions['impersonate'])->toBeFalse();
});

it('never offers impersonate on a super-admin to a non super-admin actor', function () {
    Role::findOrCreate('super-admin');
    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole('super-admin');

    $actions = userDetailActions(userDetailActor(true), $target);

    expect($actions['impersonate'])->toBeFalse();
});
