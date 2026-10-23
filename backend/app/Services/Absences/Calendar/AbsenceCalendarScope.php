<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;

/**
 * Whose absences the calendar shows (spec 0279 D-1, amended by spec 0309 D-4):
 * `calendarAll` shows everyone; `calendarOwn` shows oneself plus the active
 * subordinates in the org chart when the actor has any (no team permission);
 * neither means no calendar at all, even for a manager.
 */
enum AbsenceCalendarScope: string
{
    case Own = 'own';
    case Team = 'team';
    case All = 'all';

    public static function forActor(User $actor): ?self
    {
        if ($actor->can('absence-requests.calendarAll')) {
            return self::All;
        }

        if (! $actor->can('absence-requests.calendarOwn')) {
            return null;
        }

        return (new TimeEntrySubordinateResolver)->descendantIds($actor->id) !== [] ? self::Team : self::Own;
    }
}
