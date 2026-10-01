<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0185 D-3: the request statistics became the "Statistiche Gestione
 * Richieste" module, gated by `request-statistics.view`, and the
 * `{module}.report` ability is no longer declared by RequestManagementPolicy.
 * `permissions:sync` only ever CREATES permissions, so whoever held either
 * former grant (a role or a user directly) is given the new permission here,
 * then the two old rows are pruned — their pivot rows disappear with them
 * (cascade on the spatie pivots). `enrollee-management.report` is folded in
 * too: its holders saw statistics, and Gestione Iscritti has none any more
 * (D-1).
 *
 * `down()` is deliberately a no-op, same precedent as
 * `2026_09_29_120000_prune_receive_transfer_notifications_permissions`:
 * nothing declares the old permissions any more.
 */
return new class extends Migration
{
    private const array OLD_PERMISSIONS = ['request-management.report', 'enrollee-management.report'];

    private const string NEW_PERMISSION = 'request-statistics.view';

    public function up(): void
    {
        $old = DB::table('permissions')->whereIn('name', self::OLD_PERMISSIONS)->get(['id', 'guard_name']);

        if ($old->isEmpty()) {
            return;
        }

        // Step 1: the new permission, created here when `permissions:sync`
        // has not run yet on this installation.
        $newId = $this->permissionId((string) $old->first()->guard_name);
        $oldIds = $old->pluck('id')->all();

        // Step 2: every holder of an old grant receives the new one.
        DB::table('role_has_permissions')->whereIn('permission_id', $oldIds)->distinct()->pluck('role_id')
            ->each(fn (int $roleId) => DB::table('role_has_permissions')
                ->insertOrIgnore(['permission_id' => $newId, 'role_id' => $roleId]));

        DB::table('model_has_permissions')->whereIn('permission_id', $oldIds)->get(['model_type', 'model_id'])
            ->unique(fn (object $row): string => $row->model_type.'#'.$row->model_id)
            ->each(fn (object $row) => DB::table('model_has_permissions')->insertOrIgnore([
                'permission_id' => $newId,
                'model_type' => $row->model_type,
                'model_id' => $row->model_id,
            ]));

        // Step 3: the old grants, gone with their pivots.
        DB::table('permissions')->whereIn('id', $oldIds)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible by design: see the docblock above.
    }

    private function permissionId(string $guard): int
    {
        $existing = DB::table('permissions')->where('name', self::NEW_PERMISSION)->where('guard_name', $guard)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('permissions')->insertGetId([
            'name' => self::NEW_PERMISSION,
            'guard_name' => $guard,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
