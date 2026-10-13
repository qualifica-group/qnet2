<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;

/**
 * Whether an actor may see the legacy `old_id` of a record: super-admin
 * (Gate::before) or a role holding `legacy-ids.view` (LegacyIdPolicy). The
 * single check shared by the grid column, the grid rows and the record
 * Resources, so the id is never sent to an actor who may not see it.
 */
final class LegacyIdVisibility
{
    public const string PERMISSION = 'legacy-ids.view';

    public static function allows(?User $user): bool
    {
        return $user !== null && $user->can(self::PERMISSION);
    }
}
