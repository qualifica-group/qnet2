<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Everyone has the AI assistant (spec 0433, decision Q19): every role that
 * exists today receives `ai-assistant.use`. Taking it away from a role later
 * still removes the assistant for its members. A fresh installation (no
 * permission yet) is left to permissions:sync (navigation config) and to the
 * seeders, like the other permission migrations.
 */
return new class extends Migration
{
    private const string PERMISSION = 'ai-assistant.use';

    private const string GUARD = 'web';

    public function up(): void
    {
        // Step 1: the permission that permissions:sync created; none on a fresh installation.
        $permissionId = DB::table('permissions')->where('name', self::PERMISSION)->where('guard_name', self::GUARD)->value('id');
        if ($permissionId === null) {
            return;
        }

        // Step 2: every existing role of the guard.
        DB::table('roles')->where('guard_name', self::GUARD)->pluck('id')
            ->each(fn (int $roleId) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $permissionId, 'role_id' => $roleId]));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Rolling back leaves the grants in place: it cannot tell the roles that
     * received the permission here from those that already held it, and
     * revoking would silently remove access that was granted on purpose.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
