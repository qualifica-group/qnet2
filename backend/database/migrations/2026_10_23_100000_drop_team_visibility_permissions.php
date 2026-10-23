<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Spec 0309 (D-1): team visibility now derives from the org chart, so the four
 * "team" permissions are gone from the policies. `permissions:sync` only ever
 * creates, hence this explicit prune. The spatie pivots
 * (`role_has_permissions`, `model_has_permissions`) cascade on permission
 * delete; the spatie cache is flushed so no stale grant survives.
 *
 * `down()` is irreversible by design (same precedent as
 * 2026_08_05_120600): nothing declares these permissions any more, so
 * recreating them would only produce orphans.
 */
return new class extends Migration
{
    private const array REMOVED_PERMISSIONS = [
        'tasks.viewTeam',
        'absence-requests.calendarTeam',
        'staff-trainings.viewTeam',
        'user-skills.viewTeam',
    ];

    public function up(): void
    {
        DB::table('permissions')->whereIn('name', self::REMOVED_PERMISSIONS)->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversible by design: see the docblock above.
    }
};
