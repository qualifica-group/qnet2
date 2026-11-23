<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0412, D-4: `quotes.viewCosts` keeps today's behaviour, so every role
 * that sees the offers (`quotes.view`) receives it, except the technicians'
 * twin role that withholds the costs (QualificaTechnicianWithoutCostsSeeder)
 * and the department roles (`reparto-*`, spec 0408): their questionnaire
 * answers question 23 itself, applied by QualificaDepartmentRoleSeeder.
 * A fresh installation (no `quotes.view`) is left to permissions:sync.
 */
return new class extends Migration
{
    private const string PERMISSION = 'quotes.viewCosts';

    private const string REQUIRED_PERMISSION = 'quotes.view';

    private const string WITHHELD_ROLE = 'tecnico-consulenza-senza-costi';

    private const string DEPARTMENT_ROLE_PATTERN = 'reparto-%';

    private const string GUARD = 'web';

    public function up(): void
    {
        if (! DB::table('permissions')->where('name', self::REQUIRED_PERMISSION)->where('guard_name', self::GUARD)->exists()) {
            return;
        }

        // Step 1: the roles that see the offers today.
        $roleIds = DB::table('role_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'role_has_permissions.permission_id')
            ->join('roles', 'roles.id', '=', 'role_has_permissions.role_id')
            ->where('permissions.name', self::REQUIRED_PERMISSION)
            ->where('permissions.guard_name', self::GUARD)
            ->where('roles.name', '!=', self::WITHHELD_ROLE)
            ->where('roles.name', 'not like', self::DEPARTMENT_ROLE_PATTERN)
            ->pluck('role_has_permissions.role_id')
            ->all();

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
