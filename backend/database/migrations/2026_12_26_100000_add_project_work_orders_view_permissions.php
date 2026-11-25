<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * "Commesse di Progetto" gets its own viewing permissions (user decision
 * 2026-11-25). Until now `work-orders.view|viewAny` also opened the project
 * commesse, so every role and user holding them receives the project twin:
 * nobody loses sight of a project commessa on deploy; the administrator then
 * takes it away where it does not belong. A fresh installation (no
 * `work-orders.*` yet) is left to permissions:sync.
 */
return new class extends Migration
{
    /** "Commesse" permission => "Commesse di Progetto" twin. */
    private const array TWINS = [
        'work-orders.view' => 'project-work-orders.view',
        'work-orders.viewAny' => 'project-work-orders.viewAny',
    ];

    private const string GUARD = 'web';

    public function up(): void
    {
        foreach (self::TWINS as $source => $target) {
            $sourceId = $this->permissionId($source);

            if ($sourceId === null) {
                continue;
            }

            // Step 1: the twin, unless permissions:sync already created it.
            $targetId = $this->permissionId($target) ?? DB::table('permissions')->insertGetId([
                'name' => $target,
                'guard_name' => self::GUARD,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Step 2: every role holding the source.
            DB::table('role_has_permissions')->where('permission_id', $sourceId)->pluck('role_id')
                ->each(fn (int $roleId) => DB::table('role_has_permissions')->insertOrIgnore(['permission_id' => $targetId, 'role_id' => $roleId]));

            // Step 3: every direct grant of the source.
            DB::table('model_has_permissions')->where('permission_id', $sourceId)->get(['model_type', 'model_id'])
                ->each(fn (object $grant) => DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $targetId,
                    'model_type' => $grant->model_type,
                    'model_id' => $grant->model_id,
                ]));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('name', array_values(self::TWINS))->where('guard_name', self::GUARD)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function permissionId(string $name): ?int
    {
        $id = DB::table('permissions')->where('name', $name)->where('guard_name', self::GUARD)->value('id');

        return $id === null ? null : (int) $id;
    }
};
