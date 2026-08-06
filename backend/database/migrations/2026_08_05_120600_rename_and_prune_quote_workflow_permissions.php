<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Spec 0083 (D-1/D-6): `permissions:sync` only ever CREATES permissions
 * (mirrors `2026_08_05_110200_prune_opportunity_statuses_permissions`), so
 * once `config/authorization.php` drops the `opportunity-workflows`/
 * `quote-statuses` resources and declares `quote-workflows` instead, the
 * existing rows need an explicit rename/prune pass:
 *  - every `opportunity-workflows.*` permission is renamed IN PLACE to
 *    `quote-workflows.*`, keeping its id so any existing
 *    `role_has_permissions` pivot row survives untouched;
 *  - every `quote-statuses.*` permission is deleted outright — the module is
 *    gone, nothing declares these any more (`role_has_permissions` rows
 *    disappear with them, cascade on the spatie pivot);
 *  - `role_field_permissions.resource` mirrors both moves: renamed for the
 *    surviving module, deleted for the removed one — plus the matrix rows of
 *    the removed `opportunities.opportunity_workflow_status_id` field (spec
 *    0083 item 5).
 *
 * `down()` reverses the rename (data-preserving) but cannot resurrect the
 * deleted `quote-statuses.*` rows or the removed field's matrix rows:
 * `permissions:sync` recreates whatever the config declares, and once this
 * migration runs the config no longer declares any of them — same
 * irreversible-by-design precedent as the prune migration above.
 */
return new class extends Migration
{
    private const string OLD_WORKFLOW_RESOURCE = 'opportunity-workflows';

    private const string NEW_WORKFLOW_RESOURCE = 'quote-workflows';

    private const string REMOVED_QUOTE_STATUSES_RESOURCE = 'quote-statuses';

    public function up(): void
    {
        $this->renamePermissionPrefix(self::OLD_WORKFLOW_RESOURCE, self::NEW_WORKFLOW_RESOURCE);

        DB::table('permissions')->where('name', 'like', self::REMOVED_QUOTE_STATUSES_RESOURCE.'.%')->delete();

        DB::table('role_field_permissions')
            ->where('resource', self::OLD_WORKFLOW_RESOURCE)
            ->update(['resource' => self::NEW_WORKFLOW_RESOURCE]);

        DB::table('role_field_permissions')->where('resource', self::REMOVED_QUOTE_STATUSES_RESOURCE)->delete();

        DB::table('role_field_permissions')
            ->where('resource', 'opportunities')
            ->where('field', 'opportunity_workflow_status_id')
            ->delete();
    }

    public function down(): void
    {
        $this->renamePermissionPrefix(self::NEW_WORKFLOW_RESOURCE, self::OLD_WORKFLOW_RESOURCE);

        DB::table('role_field_permissions')
            ->where('resource', self::NEW_WORKFLOW_RESOURCE)
            ->update(['resource' => self::OLD_WORKFLOW_RESOURCE]);

        // Irreversible by design: see the docblock above.
    }

    private function renamePermissionPrefix(string $from, string $to): void
    {
        DB::table('permissions')
            ->where('name', 'like', $from.'.%')
            ->get(['id', 'name'])
            ->each(function (object $permission) use ($from, $to): void {
                DB::table('permissions')
                    ->where('id', $permission->id)
                    ->update(['name' => $to.'.'.Str::after($permission->name, $from.'.')]);
            });
    }
};
