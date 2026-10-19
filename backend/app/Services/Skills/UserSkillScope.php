<?php

declare(strict_types=1);

namespace App\Services\Skills;

use App\Models\User;

/**
 * Whose skills an actor sees (spec 0286 D-5): one permission per level, the
 * widest held wins, none means no access. `manage` (HR office) writes for
 * anyone, so it implies seeing everyone.
 */
enum UserSkillScope: string
{
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    /** Widest first, so the first permission held decides. */
    private const array PERMISSIONS = [
        'all' => ['user-skills.manage', 'user-skills.viewAll'],
        'team' => ['user-skills.viewTeam'],
        'own' => ['user-skills.viewAny'],
    ];

    public static function forActor(User $actor): ?self
    {
        foreach (self::PERMISSIONS as $scope => $permissions) {
            foreach ($permissions as $permission) {
                if ($actor->can($permission)) {
                    return self::from($scope);
                }
            }
        }

        return null;
    }
}
