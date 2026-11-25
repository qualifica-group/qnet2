<?php

use App\Models\Role;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Row actions of the roles grid keyed by role name.
 *
 * @return array<string, array<int, string>>
 */
function roleRowActions(): array
{
    return collect(test()->postJson('/api/tables/roles/rows', [
        'startRow' => 0,
        'endRow' => 25,
    ])->assertOk()->json('items'))
        ->mapWithKeys(fn (array $row): array => [$row['name'] => $row['actions']])
        ->all();
}

it('declares the duplicate action to an actor who can create roles', function () {
    Sanctum::actingAs(actorWithRoleAbilities(['viewAny', 'create']));

    $duplicate = collect($this->getJson('/api/tables/roles/columns')->assertOk()->json('data.actions'))
        ->firstWhere('key', 'duplicate');

    expect($duplicate)->not->toBeNull()
        ->and($duplicate['confirm'])->toBeFalse();
});

it('offers duplicate on a role to an actor who can create roles', function () {
    Role::create(['name' => 'editor']);
    Sanctum::actingAs(actorWithRoleAbilities(['viewAny', 'view', 'create']));

    expect(roleRowActions()['editor'])->toContain('duplicate');
});

it('hides duplicate from an actor who cannot create roles', function () {
    Role::create(['name' => 'editor']);
    Sanctum::actingAs(actorWithRoleAbilities(['viewAny', 'view']));

    expect(roleRowActions()['editor'])->not->toContain('duplicate');
});

it('never offers duplicate on the super-admin system role', function () {
    Role::create(['name' => UserService::PRIVILEGED_ROLE]);
    Role::create(['name' => 'editor']);
    Sanctum::actingAs(actorWithRoleAbilities(['viewAny', 'view', 'create']));

    $actions = roleRowActions();

    expect($actions[UserService::PRIVILEGED_ROLE])->not->toContain('duplicate')
        ->and($actions['editor'])->toContain('duplicate');
});
