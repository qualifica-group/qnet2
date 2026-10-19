<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Models\User;

/**
 * Whose absences the calendar shows (spec 0279 D-1): one permission per
 * level, the widest held wins, none means no calendar at all.
 */
enum AbsenceCalendarScope: string
{
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    /** Widest first, so the first permission held decides. */
    private const array PERMISSIONS = [
        'all' => 'absence-requests.calendarAll',
        'team' => 'absence-requests.calendarTeam',
        'own' => 'absence-requests.calendarOwn',
    ];

    public static function forActor(User $actor): ?self
    {
        foreach (self::PERMISSIONS as $scope => $permission) {
            if ($actor->can($permission)) {
                return self::from($scope);
            }
        }

        return null;
    }
}
