<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Direttiva utente 2026-09-08: Gestione Richieste exposes the FIRST G.A. slot
 * where it used to expose the third — the grid column and the bulk action
 * moved from `ManagerPositions::GA3` onto `ManagerPositions::GA1`, and every
 * identifier followed (`manager_ga3` -> `manager_ga1`,
 * `assign-manager-ga3` -> `assign-manager-ga1`, `assignManagerGa3` ->
 * `assignManagerGa1`, `manager_ga3_id` -> `manager_ga1_id`).
 *
 * Two configured tables carry the OLD names as DATA and must follow, or an
 * administrator's configuration becomes a silent orphan:
 *  - `permissions`: `permissions:sync` only ever CREATES rows (see
 *    `2026_08_05_120600_rename_and_prune_quote_workflow_permissions`), so
 *    the old ability is renamed IN PLACE — keeping its id, and with it every
 *    `role_has_permissions` grant an administrator already made;
 *  - `role_field_permissions`, keyed on (`role_id`,`resource`,`field`): the
 *    field key is renamed in place too, keeping the visible/editable/required
 *    flags — the same pass, and the same conflict rule, as
 *    `2026_09_02_230000_rename_request_management_operator_field_permission`.
 *
 * The `quote_user` PIVOT is deliberately NOT touched: no data moves between
 * positions. A row whose position 3 was filled keeps that manager where it
 * is (the work panel's whole-team editor still shows it), and the grid column
 * now reads position 1 — which is what the directive asked for.
 *
 * `down()` is the exact mirror. Both halves round-trip losslessly except a
 * pre-existing conflicting row on the TARGET name, which is dropped first:
 * nothing in this codebase can produce that state (the two names never
 * coexisted), and it is the price of honouring the unique indexes without
 * aborting.
 */
return new class extends Migration
{
    private const string RESOURCE = 'request-management';

    private const string OLD_PERMISSION = 'request-management.assignManagerGa3';

    private const string NEW_PERMISSION = 'request-management.assignManagerGa1';

    private const string OLD_FIELD = 'manager_ga3_id';

    private const string NEW_FIELD = 'manager_ga1_id';

    public function up(): void
    {
        $this->renamePermission(self::OLD_PERMISSION, self::NEW_PERMISSION);
        $this->renameField(self::OLD_FIELD, self::NEW_FIELD);
    }

    public function down(): void
    {
        $this->renamePermission(self::NEW_PERMISSION, self::OLD_PERMISSION);
        $this->renameField(self::NEW_FIELD, self::OLD_FIELD);
    }

    /**
     * Renamed in place (the id, and every grant hanging off it, survive). A
     * row already sitting on the target name is deleted first: `permissions`
     * is unique on (`name`,`guard_name`), so the update would otherwise
     * abort the whole migration.
     */
    private function renamePermission(string $from, string $to): void
    {
        $source = DB::table('permissions')->where('name', $from)->get(['id', 'guard_name']);

        foreach ($source as $permission) {
            DB::table('permissions')
                ->where('name', $to)
                ->where('guard_name', $permission->guard_name)
                ->delete();

            DB::table('permissions')->where('id', $permission->id)->update(['name' => $to]);
        }
    }

    /**
     * Two statements rather than one: MySQL forbids a DELETE whose subquery
     * selects from the very table being deleted from, so the conflicting role
     * ids are READ first and handed over as plain values — identical
     * behaviour on MySQL (prod) and SQLite (tests).
     */
    private function renameField(string $from, string $to): void
    {
        $conflictingRoleIds = DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->pluck('role_id')
            ->all();

        if ($conflictingRoleIds !== []) {
            DB::table('role_field_permissions')
                ->where('resource', self::RESOURCE)
                ->where('field', $to)
                ->whereIn('role_id', $conflictingRoleIds)
                ->delete();
        }

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->update(['field' => $to]);
    }
};
