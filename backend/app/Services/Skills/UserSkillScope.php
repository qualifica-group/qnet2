<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;

/**
 * Whose skills an actor sees (spec 0286 D-5, amended by spec 0309 D-2): `manage` or
 * `viewAll` see everyone; having active subordinates in the org chart sees
 * oneself plus them, with no permission needed; `viewAny` alone sees one's own;
 * none of these means no access. `manage` (HR office) writes for anyone.
 */
enum UserSkillScope: string
{
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    private const array ALL_PERMISSIONS = ['user-skills.manage', 'user-skills.viewAll'];

    private const string OWN_PERMISSION = 'user-skills.viewAny';

    public static function forActor(User $actor): ?self
    {
        foreach (self::ALL_PERMISSIONS as $permission) {
            if ($actor->can($permission)) {
                return self::All;
            }
        }

        if ((new TimeEntrySubordinateResolver)->descendantIds($actor->id) !== []) {
            return self::Team;
        }

        return $actor->can(self::OWN_PERMISSION) ? self::Own : null;
    }
}
