<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0420, D-4: the assignment notifications keep today's behaviour, so
 * every operational role (one that sees at least one module, any `*.view`)
 * receives both notification permissions (bell and email) of every module:
 * whoever was notified before still is, even when the role does not see the
 * module (e.g. a consultant put in charge of an offer). Single-purpose
 * add-on roles stay as they are. Frozen lists on purpose: a migration must
 * not follow later changes of the application classes.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const array RESOURCES = ['opportunities', 'quotes', 'request-management', 'enrollee-management'];

    /** @var array<int, string> */
    private const array ABILITIES = ['notifyAssignment', 'notifyAssignmentEmail'];

    private const string VIEW_PATTERN = '%.view';

    private const string GUARD = 'web';

    public function up(): void
    {
        // Step 1: the operational roles; a fresh installation (none yet) is left to permissions:sync.
        $roleIds = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->where('permissions.name', 'like', self::VIEW_PATTERN)
            ->where('permissions.guard_name', self::GUARD)
            ->distinct()
            ->pluck('role_has_permissions.role_id')
            ->all();

        if ($roleIds === []) {
            return;
        }

        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                // Step 2: the permission, unless permissions:sync already created it.
                $permissionId = $this->permissionId("{$resource}.{$ability}");

                // Step 3: the grant.
                foreach ($roleIds as $roleId) {
                    DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]);
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                DB::table('permissions')->where('name', "{$resource}.{$ability}")->where('guard_name', self::GUARD)->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(string $name): int
    {
        return (int) (DB::table('permissions')->where('name', $name)->where('guard_name', self::GUARD)->value('id')
            ?? DB::table('permissions')->insertGetId([
                'name' => $name,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }
};
