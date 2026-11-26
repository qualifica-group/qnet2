<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function aiGrantMigration(): Migration
{
    return require database_path('migrations/2026_12_29_100000_grant_ai_assistant_use_to_all_roles.php');
}

function aiGrantCanUse(User $user): bool
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    aiapi_configure();
    Sanctum::actingAs($user->fresh());

    return test()->getJson('/api/ai-assistant/config')->status() === 200;
}

/** The permission as permissions:sync creates it from the navigation config. */
function aiGrantSyncedPermission(): Permission
{
    return Permission::findOrCreate('ai-assistant.use', 'web');
}

it('spec 0433: the migration gives ai-assistant.use to every existing role', function () {
    $roles = collect(['support', 'sales'])->map(fn (string $name) => Role::create(['name' => $name]));
    $member = User::factory()->create()->assignRole('support');
    $permissionId = aiGrantSyncedPermission()->id;

    aiGrantMigration()->up();
    aiGrantMigration()->up(); // idempotent

    expect(DB::table('role_has_permissions')->where('permission_id', $permissionId)->pluck('role_id')->sort()->values()->all())
        ->toBe($roles->pluck('id')->sort()->values()->all())
        ->and(aiGrantCanUse($member))->toBeTrue();
});

// REQUIREMENT CHANGED (merge with staging): like the other permission migrations, a fresh installation is left to
// permissions:sync and the seeders; creating the permission here put it in every test database.
it('spec 0433: on a fresh installation the migration creates nothing', function () {
    Role::create(['name' => 'support']);
    Permission::query()->where('name', 'ai-assistant.use')->delete();

    aiGrantMigration()->up();

    expect(Permission::query()->where('name', 'ai-assistant.use')->exists())->toBeFalse()
        ->and(DB::table('role_has_permissions')->count())->toBe(0);
});

it('spec 0433: taking the permission away from a role takes the assistant away; a role without it is refused', function () {
    Role::create(['name' => 'support']);
    $member = User::factory()->create()->assignRole('support');
    aiGrantSyncedPermission();
    aiGrantMigration()->up();
    expect(aiGrantCanUse($member))->toBeTrue();

    Role::findByName('support', 'web')->revokePermissionTo('ai-assistant.use');

    expect(aiGrantCanUse($member))->toBeFalse();
});

it('spec 0433: a role created later with the permission selected has the assistant', function () {
    aiGrantSyncedPermission();
    aiGrantMigration()->up();
    $role = Role::create(['name' => 'newcomers']);
    $role->givePermissionTo('ai-assistant.use');
    $member = User::factory()->create()->assignRole('newcomers');

    expect(aiGrantCanUse($member))->toBeTrue();
});

it('spec 0433: the migration rollback runs without revoking anything', function () {
    Role::create(['name' => 'support']);
    aiGrantSyncedPermission();
    aiGrantMigration()->up();

    aiGrantMigration()->down();

    expect(DB::table('role_has_permissions')->count())->toBe(1);
});
