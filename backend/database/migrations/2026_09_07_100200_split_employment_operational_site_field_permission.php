<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0103 (D-9): the `users` field-permission key
 * `employment.operational_site_id` is replaced by TWO keys —
 * `employment.primary_operational_site_id` and
 * `employment.remote_operational_site_ids` (UsersAuthorization::fields()) —
 * because the single site column the old key mirrored no longer exists (M2
 * dropped `employment_profiles.operational_site_id` for the
 * `employment_profile_operational_site` pivot). Without this pass every row
 * an administrator already configured on the old key would become a silent
 * orphan: the matrix would read as "unrestricted" for both new fields
 * (AC-015). Each old row is therefore SPLIT into two rows carrying the SAME
 * visible/editable/required flags, then the original is deleted.
 *
 * The UNIQUE index on (role_id, resource, field) makes an insert fail if a
 * role somehow already carries a row on one of the new keys — nothing writes
 * one today (same reasoning as the precedent below), but a hand-edited or
 * partially-migrated database could. Such a row is dropped FIRST, mirroring
 * 2026_09_02_230000_rename_request_management_operator_field_permission.php.
 *
 * down() collapses the two keys back into one, using the PRIMARY key's rows
 * as the canonical source (renamed back to the old key) and discarding the
 * REMOTE key's rows outright. That is lossless for a straight up-then-down
 * round trip, where both halves of the split still carry identical flags;
 * it only loses information if an administrator edited the two keys
 * DIFFERENTLY in between — a state this migration's own up() never produces
 * and that only a hand-edit could reach.
 */
return new class extends Migration
{
    private const string RESOURCE = 'users';

    private const string OLD_FIELD = 'employment.operational_site_id';

    private const string PRIMARY_FIELD = 'employment.primary_operational_site_id';

    private const string REMOTE_FIELD = 'employment.remote_operational_site_ids';

    public function up(): void
    {
        $rows = DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', self::OLD_FIELD)
            ->get();

        foreach ([self::PRIMARY_FIELD, self::REMOTE_FIELD] as $newField) {
            $this->clearConflicts($newField, $rows->pluck('role_id')->all());

            foreach ($rows as $row) {
                DB::table('role_field_permissions')->insert([
                    'role_id' => $row->role_id,
                    'resource' => self::RESOURCE,
                    'field' => $newField,
                    'visible' => $row->visible,
                    'editable' => $row->editable,
                    'required' => $row->required,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }
        }

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', self::OLD_FIELD)
            ->delete();
    }

    public function down(): void
    {
        $conflictingRoleIds = DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', self::PRIMARY_FIELD)
            ->pluck('role_id')
            ->all();

        $this->clearConflicts(self::OLD_FIELD, $conflictingRoleIds);

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', self::PRIMARY_FIELD)
            ->update(['field' => self::OLD_FIELD]);

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', self::REMOTE_FIELD)
            ->delete();
    }

    /**
     * Deletes any pre-existing row on $field for the given role ids, so the
     * upcoming insert/rename never trips the (role_id, resource, field)
     * unique index.
     *
     * @param  array<int, int>  $roleIds
     */
    private function clearConflicts(string $field, array $roleIds): void
    {
        if ($roleIds === []) {
            return;
        }

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $field)
            ->whereIn('role_id', $roleIds)
            ->delete();
    }
};
