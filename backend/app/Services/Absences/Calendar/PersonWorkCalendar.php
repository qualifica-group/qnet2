<?php

declare(strict_types=1);

namespace App\Services\Absences\Calendar;

use App\Enums\CompanyClosureKind;
use App\Models\CompanyClosure;
use App\Services\TimeEntries\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The single place that answers "is this a working day for this person"
 * (spec 0304 D-13/D-14): WorkCalendar plus the holiday closures valid for the
 * person, i.e. for all sites or for at least one of the person's operational
 * sites (a person without sites only gets the all-sites ones). A yearly
 * closure repeats on the same month-day from its first year on.
 *
 * `load()` costs three queries whatever the number of people.
 */
final class PersonWorkCalendar
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * @param  array<int, int>  $userIds
     */
    public function load(array $userIds, string $from, string $to): PersonNonWorkingDays
    {
        // Step 1: the holiday closures that can touch the period
        $closures = $this->closures($from, $to);

        if ($closures->isEmpty() || $userIds === []) {
            return new PersonNonWorkingDays($this->calendar, []);
        }

        // Step 2: the sites of each person, then the closed days per person
        $sitesByUser = $this->siteIdsByUser($userIds);
        $byUser = [];

        foreach ($userIds as $userId) {
            $userSites = $sitesByUser[$userId] ?? [];

            foreach ($closures as $closure) {
                if (! $closure->appliesToSiteIds($userSites)) {
                    continue;
                }

                foreach ($this->datesIn($closure, $from, $to) as $date) {
                    $byUser[$userId][$date] = $closure->name;
                }
            }
        }

        return new PersonNonWorkingDays($this->calendar, $byUser);
    }

    /**
     * @return Collection<int, CompanyClosure>
     */
    private function closures(string $from, string $to): Collection
    {
        return CompanyClosure::query()
            ->where('kind', CompanyClosureKind::Holiday->value)
            ->where(fn ($query) => $query
                ->where(fn ($range) => $range->where('starts_on', '<=', $to)->where('ends_on', '>=', $from))
                ->orWhere('repeats_yearly', true))
            ->with('operationalSites:id')
            ->get();
    }

    /**
     * @param  array<int, int>  $userIds
     * @return array<int, array<int, int>> user id => operational site ids
     */
    public function siteIdsByUser(array $userIds): array
    {
        $map = [];

        $rows = DB::table('employment_profiles')
            ->join('employment_profile_operational_site', 'employment_profile_operational_site.employment_profile_id', '=', 'employment_profiles.id')
            ->whereIn('employment_profiles.user_id', $userIds)
            ->get(['employment_profiles.user_id', 'employment_profile_operational_site.operational_site_id']);

        foreach ($rows as $row) {
            $map[(int) $row->user_id][] = (int) $row->operational_site_id;
        }

        return $map;
    }

    /**
     * @return list<string> the closure dates that fall inside [from, to]
     */
    private function datesIn(CompanyClosure $closure, string $from, string $to): array
    {
        $start = $closure->starts_on->format('Y-m-d');
        $dates = [];

        if ($closure->repeats_yearly) {
            $monthDay = substr($start, 5);

            for ($year = max((int) substr($start, 0, 4), (int) substr($from, 0, 4)); $year <= (int) substr($to, 0, 4); $year++) {
                $date = "{$year}-{$monthDay}";

                if ($date >= $from && $date <= $to && $date >= $start) {
                    $dates[] = $date;
                }
            }

            return $dates;
        }

        $end = min($closure->ends_on->format('Y-m-d'), $to);

        for ($day = CarbonImmutable::parse(max($start, $from)); $day->format('Y-m-d') <= $end; $day = $day->addDay()) {
            $dates[] = $day->format('Y-m-d');
        }

        return $dates;
    }
}
