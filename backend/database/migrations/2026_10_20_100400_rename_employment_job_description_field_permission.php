<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0271 (D-10): the `users` field-permission key
 * `employment.job_description` (text) is replaced by
 * `employment.job_role_ids` (multiselect), since the column it mirrored is
 * gone. Each `role_field_permissions` row is RENAMED in place, keeping its
 * flags and id, so a restriction an administrator configured keeps applying
 * (AC-013). Same approach as
 * `2026_09_25_100200_rename_employment_reports_to_field_permission`: a row
 * already present on the target key is dropped first (UNIQUE role_id,
 * resource, field), and `down()` is the exact mirror.
 */
return new class extends Migration
{
    private const string RESOURCE = 'users';

    private const string OLD_FIELD = 'employment.job_description';

    private const string NEW_FIELD = 'employment.job_role_ids';

    public function up(): void
    {
        $this->renameField(self::OLD_FIELD, self::NEW_FIELD);
    }

    public function down(): void
    {
        $this->renameField(self::NEW_FIELD, self::OLD_FIELD);
    }

    /**
     * Two statements: MySQL forbids a DELETE whose subquery reads the table
     * being deleted from, so the conflicting role ids are read first.
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
