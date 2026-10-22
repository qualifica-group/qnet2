<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Models\User;
use App\Services\TimeEntries\TimeEntrySubordinateResolver;

/**
 * The people whose absences an actor sees in the calendar (spec 0279 D-1),
 * as the single answer reused by the colleagues of a request and the
 * "absent today" list (spec 0304): nothing wider than the calendar reveals.
 */
final class AbsenceCalendarPeople
{
    public function __construct(private readonly TimeEntrySubordinateResolver $subordinates) {}

    /**
     * @return list<int>|null user ids, `null` = every active person, `[]` = no calendar permission
     */
    public function visibleUserIds(User $actor): ?array
    {
        return match (AbsenceCalendarScope::forActor($actor)) {
            AbsenceCalendarScope::All => null,
            AbsenceCalendarScope::Team => [$actor->id, ...$this->subordinates->descendantIds($actor->id)],
            AbsenceCalendarScope::Own => [$actor->id],
            default => [],
        };
    }
}
