<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0272 (D-3): the `qualification_type` column is gone (see
 * 2026_10_21_100600_drop_qualification_type_from_employment_profiles_table),
 * so the `users` field-permission key `employment.qualification_type` no
 * longer exists in the catalogue. Its `role_field_permissions` rows are
 * deleted so the role matrix does not keep orphan configuration.
 *
 * Not restorable: the rows carried per-role flags of a field that has no
 * catalogue entry (and no column) any more, so down() is deliberately a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_field_permissions')
            ->where('resource', 'users')
            ->where('field', 'employment.qualification_type')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally empty: see the class docblock.
    }
};
