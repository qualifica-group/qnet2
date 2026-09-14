<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\Models\EmploymentProfile;

/**
 * D-7's target formula, calendar-agnostic on purpose: `max(0, standard -
 * coalesce(break, 0))`, falling back to `config('time_entries.
 * default_daily_minutes')` when there is no profile or `standard_daily_minutes`
 * is null. Takes the (possibly null) profile directly rather than a User, so
 * it is a pure unit with zero DB access — callers eager-load `employment`
 * once per request (`TimeEntryDaySetBuilder`) and pass it in.
 *
 * The WORKING-DAY zeroing (D-7: non-working day -> target 0) is NOT this
 * class's job — it stays in `TimeEntryDayBuilder`, which already holds the
 * `WorkCalendar` collaborator, so the profile-based figure computed here
 * doubles as `meta.daily_target_minutes` ("target di oggi ... anche se oggi
 * e' festivo usa il valore da profilo") without a second code path.
 */
final class DailyTargetResolver
{
    public function resolve(?EmploymentProfile $profile): int
    {
        if ($profile === null || $profile->standard_daily_minutes === null) {
            return (int) config('time_entries.default_daily_minutes');
        }

        return max(0, $profile->standard_daily_minutes - ($profile->break_daily_minutes ?? 0));
    }
}
