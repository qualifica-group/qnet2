<?php

use App\Models\Role;
use Database\Seeders\DemoRolesSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the non-privileged application roles with coherent permission sets', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);

    $admin = Role::findByName('admin');
    $manager = Role::findByName('manager');
    $operator = Role::findByName('operator');
    $user = Role::findByName('user');
    $viewer = Role::findByName('viewer');

    expect($admin->permissions)->not->toBeEmpty()
        ->and($admin->permissions->pluck('name'))->toContain('users.delete', 'roles.delete')
        ->and($manager->permissions->pluck('name'))->toContain('users.create', 'users.update', 'roles.view')
        ->and($manager->permissions->pluck('name'))->not->toContain('roles.delete', 'users.delete')
        ->and($operator->permissions->pluck('name'))->toContain('personal_data.update')
        ->and($operator->permissions->pluck('name'))->not->toContain('roles.view')
        ->and($user->permissions->pluck('name'))->toContain('personal_data.view')
        ->and($user->permissions->pluck('name'))->not->toContain('users.update', 'roles.view')
        ->and($viewer->permissions->pluck('name'))->toContain('users.viewAny', 'users.view', 'roles.viewAny', 'roles.view')
        ->and($viewer->permissions->pluck('name')->every(
            fn (string $permission): bool => str_ends_with($permission, '.viewAny') || str_ends_with($permission, '.view')
        ))->toBeTrue();
});

it('AC-018: registries mirrors companies in the manager/operator/user/viewer matrices (spec 0020)', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);

    $manager = Role::findByName('manager');
    $operator = Role::findByName('operator');
    $user = Role::findByName('user');
    $viewer = Role::findByName('viewer');

    // The abilities a role holds on $resource, as a bare (unprefixed) sorted list.
    $abilitiesOn = fn (Role $role, string $resource): array => $role->permissions->pluck('name')
        ->filter(fn (string $permission): bool => str_starts_with($permission, "{$resource}."))
        ->map(fn (string $permission): string => substr($permission, strlen($resource) + 1))
        ->sort()->values()->all();

    expect($abilitiesOn($manager, 'registries'))->toBe($abilitiesOn($manager, 'companies'))
        ->and($abilitiesOn($operator, 'registries'))->toBe($abilitiesOn($operator, 'companies'))
        ->and($abilitiesOn($user, 'registries'))->toBe($abilitiesOn($user, 'companies'))
        ->and($viewer->permissions->pluck('name'))->toContain('registries.viewAny', 'registries.view')
        ->and($viewer->permissions->pluck('name'))->not->toContain('registries.create', 'registries.update', 'registries.delete');
});
