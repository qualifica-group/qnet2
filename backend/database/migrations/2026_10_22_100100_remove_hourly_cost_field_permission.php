<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0277 (D-4): the `employment.hourly_cost` field left the `users`
 * catalogue with its column (see
 * 2026_10_22_100000_drop_hourly_cost_from_employment_profiles_table), so its
 * `role_field_permissions` rows are deleted: the role matrix keeps no orphan
 * configuration.
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
            ->where('field', 'employment.hourly_cost')
            ->delete();
    }

    public function down(): void
    {
        // Intentionally empty: see the class docblock.
    }
};
