<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0166 (D-8): the `users` field-permission key `employment.reports_to_id`
 * (select) is replaced by `employment.reports_to_ids` (multiselect) —
 * UsersAuthorization::fields() no longer declares the old key, since the
 * column it mirrored is gone (M2 dropped `employment_profiles.reports_to_id`
 * for the `employment_profile_manager` pivot). Without this pass every row an
 * administrator already configured on the old key would become a silent
 * orphan: the matrix would read as "unrestricted" for the new key (AC-002).
 * Each row is RENAMED in place, keeping its visible/editable/required flags
 * and its id — same style as
 * `2026_09_02_230000_rename_request_management_operator_field_permission`.
 *
 * The UNIQUE index on (role_id, resource, field) makes the rename fail if a
 * role somehow already carries a row on the new key — nothing writes one
 * today, but a hand-edited or partially-migrated database could. Such a row
 * is dropped FIRST, mirroring the same precedent.
 *
 * `down()` is the exact mirror; lossless for a straight up-then-down round
 * trip, and only loses a hand-edited conflicting row on the target key,
 * exactly like the precedent it mirrors.
 */
return new class extends Migration
{
    private const string RESOURCE = 'users';

    private const string OLD_FIELD = 'employment.reports_to_id';

    private const string NEW_FIELD = 'employment.reports_to_ids';

    public function up(): void
    {
        $this->renameField(self::OLD_FIELD, self::NEW_FIELD);
    }

    public function down(): void
    {
        $this->renameField(self::NEW_FIELD, self::OLD_FIELD);
    }

    /**
     * Two statements rather than one: MySQL forbids a DELETE whose subquery
     * selects from the very table being deleted from, so the conflicting
     * role ids are READ first and handed over as plain values — identical
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
