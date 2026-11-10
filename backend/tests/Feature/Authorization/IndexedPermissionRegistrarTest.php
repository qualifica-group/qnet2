<?php

use App\Authorization\IndexedPermissionRegistrar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Every Gate check resolves its permission by name: the lookup must stay
 * constant-time on the catalogue size and must keep the package's semantics,
 * freshness included.
 */
uses(RefreshDatabase::class);

const INDEXED_REGISTRAR_GUARD = 'web';

it('is the registrar the container resolves', function () {
    expect(app(PermissionRegistrar::class))->toBeInstanceOf(IndexedPermissionRegistrar::class);
});

it('finds a permission by name and guard', function () {
    $permission = Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);

    expect(Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD)->getKey())->toBe($permission->getKey());
});

it('keeps the same name on two guards apart', function () {
    $web = Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    $other = Permission::create(['name' => 'tags.view', 'guard_name' => 'other']);

    expect(Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD)->getKey())->toBe($web->getKey())
        ->and(Permission::findByName('tags.view', 'other')->getKey())->toBe($other->getKey());
});

it('throws for an unknown name, as the policy abilities rely on', function () {
    Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);

    Permission::findByName('update', INDEXED_REGISTRAR_GUARD);
})->throws(PermissionDoesNotExist::class);

it('sees a permission created after the first lookup', function () {
    Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD);

    $created = Permission::create(['name' => 'tags.update', 'guard_name' => INDEXED_REGISTRAR_GUARD]);

    expect(Permission::findByName('tags.update', INDEXED_REGISTRAR_GUARD)->getKey())->toBe($created->getKey());
});

it('stops finding a permission deleted after the first lookup', function () {
    $permission = Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD);

    $permission->delete();

    Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD);
})->throws(PermissionDoesNotExist::class);

it('sees a rename after the first lookup', function () {
    $permission = Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD);

    $permission->update(['name' => 'tags.show']);

    expect(Permission::findByName('tags.show', INDEXED_REGISTRAR_GUARD)->getKey())->toBe($permission->getKey());
});

it('rebuilds after the in-memory collection is cleared', function () {
    Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD);

    app(PermissionRegistrar::class)->clearPermissionsCollection();

    expect(Permission::findByName('tags.view', INDEXED_REGISTRAR_GUARD)->name)->toBe('tags.view');
});

it('still answers the lookups by other attributes', function () {
    $permission = Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);

    expect(Permission::findById($permission->getKey(), INDEXED_REGISTRAR_GUARD)->name)->toBe('tags.view')
        ->and(app(PermissionRegistrar::class)->getPermissions())->toHaveCount(1);
});

it('keeps the Gate verdicts of a user through grant and revoke', function () {
    Permission::create(['name' => 'tags.view', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    Permission::create(['name' => 'tags.update', 'guard_name' => INDEXED_REGISTRAR_GUARD]);
    $user = User::factory()->create();

    expect($user->can('tags.view'))->toBeFalse();

    $user->givePermissionTo('tags.view');

    expect($user->fresh()->can('tags.view'))->toBeTrue()
        ->and($user->fresh()->can('tags.update'))->toBeFalse();

    $user->revokePermissionTo('tags.view');

    expect($user->fresh()->can('tags.view'))->toBeFalse();
});
