<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0082, AC-011: `permissions:sync` only ever CREATES permissions, so
 * removing the `opportunity-statuses` resource from config/authorization.php
 * and its navigation entry leaves the rows behind. Prune them here, together
 * with the per-role field-permission matrix rows of the removed module and of
 * the removed `opportunities.opportunity_status_id` field.
 *
 * `role_has_permissions` rows disappear with their permission (cascade on the
 * spatie pivot). `down()` is deliberately a no-op: `permissions:sync`
 * recreates whatever the config declares, and the config no longer declares
 * any of this.
 */
return new class extends Migration
{
    private const string RESOURCE = 'opportunity-statuses';

    public function up(): void
    {
        DB::table('permissions')->where('name', 'like', self::RESOURCE.'.%')->delete();

        DB::table('role_field_permissions')->where('resource', self::RESOURCE)->delete();

        DB::table('role_field_permissions')
            ->where('resource', 'opportunities')
            ->where('field', 'opportunity_status_id')
            ->delete();
    }

    public function down(): void
    {
        // Irreversible by design: nothing declares these permissions any more.
    }
};
