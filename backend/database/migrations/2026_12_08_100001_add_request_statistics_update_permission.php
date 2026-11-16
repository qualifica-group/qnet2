<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0391 D-5: `request-statistics.update` saves which statuses the
 * statistics columns count. Given to the roles that both see the statistics
 * and manage the working statuses (`quote-workflows.update`).
 * `permissions:sync` would create the row too (RequestStatisticsPolicy), but
 * never grants it.
 */
return new class extends Migration
{
    private const string PERMISSION = 'request-statistics.update';

    private const array REQUIRED_PERMISSIONS = ['request-statistics.view', 'quote-workflows.update'];

    private const string GUARD = 'web';

    public function up(): void
    {
        // Step 1: the roles holding both required permissions. None (a fresh
        // installation): nothing to grant, permissions:sync creates the row.
        $holders = array_map(
            static fn (string $name): array => DB::table('role_has_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
                ->where('permissions.name', $name)
                ->where('permissions.guard_name', self::GUARD)
                ->pluck('role_id')
                ->all(),
            self::REQUIRED_PERMISSIONS,
        );
        $roleIds = array_intersect(...$holders);

        if ($roleIds === []) {
            return;
        }

        // Step 2: the permission, unless permissions:sync already created it.
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', self::GUARD)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => self::PERMISSION,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // Step 3: the grant.
        foreach ($roleIds as $roleId) {
            DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', self::GUARD)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
