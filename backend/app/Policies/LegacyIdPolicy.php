<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Permission catalogue entry for the legacy `old_id` (the external-migration
 * anchor): `legacy-ids.view` lets a role see it on grids and record details.
 *
 * Not bound to a model — there is no legacy-id record to authorize. It exists
 * so SyncPermissions creates exactly `legacy-ids.view` (abilities() override,
 * same pattern as TableFilterViewPolicy), assignable from the Role form via
 * config('authorization.permission_only_resources'). The check itself lives in
 * LegacyIdVisibility; super-admin passes through the global Gate::before.
 */
class LegacyIdPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'legacy-ids';
    }

    /**
     * @return array<int, string>
     */
    public static function abilities(): array
    {
        return ['view'];
    }
}
