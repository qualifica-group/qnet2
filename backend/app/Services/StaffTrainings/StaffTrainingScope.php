<?php

declare(strict_types=1);

namespace App\Services\StaffTrainings;

use App\Models\User;

/**
 * Whose trainings an actor sees (spec 0284 D-5): one permission per level, the
 * widest held wins, none means no access. `manage` (HR office) writes for
 * anyone, so it implies seeing everyone.
 */
enum StaffTrainingScope: string
{
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    /** Widest first, so the first permission held decides. */
    private const array PERMISSIONS = [
        'all' => ['staff-trainings.manage', 'staff-trainings.viewAll'],
        'team' => ['staff-trainings.viewTeam'],
        'own' => ['staff-trainings.viewAny'],
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
