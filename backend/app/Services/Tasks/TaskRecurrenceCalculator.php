<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use App\Enums\TaskRecurrenceMonthMode;
use App\Services\TimeEntries\WorkCalendar;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pure calendar math for a recurrence rule (spec 0120, D-1/D-2/D-15; spec
 * 0155, D-1 adds `yearly`/`custom`, the ordinal month/year shape and
 * `workdays_only`): no database, no implicit `now()` — every date the rule
 * needs is a parameter, which is what makes the calendar edge cases (D-2's
 * month-end clamp, a leap February, AC-001's missing 5th weekday) testable
 * without a single fixture.
 *
 * `$from` doubles as BOTH the starting point of the returned dates AND the
 * alignment anchor for weekly/monthly/yearly interval math (which week/
 * month/year "counts" as zero). This is safe to call again and again with
 * $from set to the LAST date a previous call returned, because any date this
 * class produces is itself exactly `k * interval` weeks/months/years past
 * whatever anchor produced it — restarting the count from it lands on the
 * same absolute calendar as continuing from the original anchor would (the
 * pattern is self-similar from any of its own occurrences, not only the
 * first). App\Console\Commands\GenerateTaskRecurrences relies on exactly
 * this to resume from `generated_until` instead of recomputing a series'
 * whole history on every run.
 *
 * Spec 0160, D-1: a `workdaysOnly` candidate landing on a non-working day
 * (weekend, national holiday, Pasqua/Pasquetta — WorkCalendar is the ONE
 * source of truth, reused rather than duplicated) is no longer skipped
 * outright (REQUIREMENT CHANGED from spec 0155) — it is SHIFTED forward to
 * the first working day after it. D-2: a shift landing on a date some other
 * candidate already produced collapses into that single occurrence, never
 * double-counted. D-3: `ends: on_date` compares against the SHIFTED date.
 */
final class TaskRecurrenceCalculator
{
    public function __construct(private readonly WorkCalendar $calendar) {}

    /**
     * Defensive circuit breaker only — interval >= 1 guarantees each
     * iteration moves strictly forward, so `ends: never` with no $horizon is
     * the only way to approach this, and only if $limit is set absurdly
     * high by a caller bug. A skipped ordinal period (AC-001) or a
     * `workdays_only` weekend (AC-002) also spends one iteration without
     * producing a date, which is exactly what this breaker exists to bound.
     */
    private const int MAX_ITERATIONS = 100_000;

    /**
     * The next occurrence dates strictly AFTER $from, in chronological
     * order, honouring the rule's own `ends` condition (D-1).
     *
     * $alreadyGenerated is the count of occurrences that exist BEFORE the
     * first date this call could return, capostipite included (D-4) — it
     * only matters when `ends` is `after_count`, to know the remaining
     * budget. $horizon, when given, additionally stops generation at the
     * first candidate strictly after it (the command's "entro oggi" cutoff,
     * D-8). Whichever of $limit, `ends` or $horizon is hit first wins.
     *
     * @return list<CarbonImmutable>
     *
     * @throws InvalidArgumentException when `interval` is not >= 1 (AC-009)
     */
    public function nextDates(
        TaskRecurrenceData $rule,
        CarbonImmutable $from,
        int $limit,
        int $alreadyGenerated = 1,
        ?CarbonImmutable $horizon = null,
    ): array {
        if ($rule->interval < 1) {
            throw new InvalidArgumentException('The recurrence interval must be at least 1.');
        }

        $dates = [];
        $cursor = $from;
        $generatedCount = $alreadyGenerated;

        for ($i = 0; $i < self::MAX_ITERATIONS && count($dates) < $limit; $i++) {
            // Step 1: advance the cursor one period forward. For
            // monthly/yearly $occurrence can be null (AC-001: the period has
            // no such Nth weekday) while $cursor itself still moves to that
            // period's start, so the NEXT call correctly targets the
            // following one instead of retrying the same period forever.
            [$cursor, $occurrence] = $this->nextCandidate($rule, $from, $cursor);

            // Step 2: $cursor is always <= whatever $occurrence a period
            // could produce (the period's own start, at the earliest), so
            // checking it here is a safe, period-granular early exit even
            // when this iteration itself yielded no date.
            if ($horizon !== null && $cursor->gt($horizon)) {
                break;
            }

            if ($occurrence === null) {
                continue;
            }

            // Step 3 (spec 0160, D-1): shift a `workdays_only` candidate
            // off any non-working day onto the first working day after it.
            if ($rule->workdaysOnly) {
                $occurrence = $this->shiftToWorkday($occurrence);
            }

            // Step 3b: the shift only ever moves forward, so $occurrence can
            // now sit past $horizon even though the pre-shift $cursor did
            // not — the same period-granular early exit as Step 2, reapplied
            // to the date actually produced.
            if ($horizon !== null && $occurrence->gt($horizon)) {
                break;
            }

            // Step 3c (D-2): a shift may land on the date the PREVIOUS
            // candidate already produced (its own shift, or its own
            // unshifted value) — one occurrence, never double-counted.
            if ($rule->workdaysOnly && $dates !== [] && $occurrence->eq(end($dates))) {
                continue;
            }

            // Step 3d (D-3): the "ends on a date" ceiling compares against
            // the SHIFTED date, never the pre-shift candidate.
            if ($rule->ends === TaskRecurrenceEnd::OnDate && $occurrence->gt(CarbonImmutable::parse($rule->endsOn))) {
                break;
            }

            if ($rule->ends === TaskRecurrenceEnd::AfterCount && $generatedCount >= $rule->occurrenceCount) {
                break;
            }

            $dates[] = $occurrence;
            $generatedCount++;
        }

        return $dates;
    }

    /**
     * @return array{0: CarbonImmutable, 1: ?CarbonImmutable} the new cursor
     *                                                        position, and the occurrence it produced (null when the
     *                                                        period has none — monthly/yearly ordinal only, AC-001)
     */
    private function nextCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): array
    {
        return match ($rule->frequency) {
            // Spec 0155, D-1: `custom` is q-net's own "every N days" alias
            // for `daily` — identical arithmetic, `interval` is the only
            // field either one reads.
            TaskRecurrenceFrequency::Daily, TaskRecurrenceFrequency::Custom => [
                $next = $cursor->addDays($rule->interval), $next,
            ],
            TaskRecurrenceFrequency::Weekly => [
                $next = $this->nextWeeklyCandidate($rule, $anchor, $cursor), $next,
            ],
            TaskRecurrenceFrequency::Monthly => $this->nextMonthlyCandidate($rule, $anchor, $cursor),
            TaskRecurrenceFrequency::Yearly => $this->nextYearlyCandidate($rule, $anchor, $cursor),
        };
    }

    /**
     * The next date, strictly after $cursor, whose ISO weekday (Monday = 1,
     * D-1) is one of the rule's `weekdays` AND whose week falls exactly
     * `k * interval` weeks after $anchor's own week — walked one day at a
     * time, which is fine given a week has 7 candidates at most to check.
     */
    private function nextWeeklyCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): CarbonImmutable
    {
        $anchorWeekStart = $anchor->startOfWeek(CarbonInterface::MONDAY);
        $candidate = $cursor->addDay();

        while (true) {
            $isEligibleWeekday = in_array($candidate->dayOfWeekIso, $rule->weekdays ?? [], true);

            if ($isEligibleWeekday) {
                $weekStart = $candidate->startOfWeek(CarbonInterface::MONDAY);
                $weeksSinceAnchor = intdiv((int) $anchorWeekStart->diffInDays($weekStart), 7);

                if ($weeksSinceAnchor % $rule->interval === 0) {
                    return $candidate;
                }
            }

            $candidate = $candidate->addDay();
        }
    }

    /**
     * The next anchor-aligned month strictly after $cursor's own month (D-2),
     * as the period's own 1st-of-month (the new cursor position) paired with
     * whatever that period resolves to (fixed day or ordinal weekday,
     * possibly none — AC-001).
     *
     * @return array{0: CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function nextMonthlyCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): array
    {
        $anchorMonthIndex = $anchor->year * 12 + ($anchor->month - 1);
        $cursorMonthIndex = $cursor->year * 12 + ($cursor->month - 1);

        $stepsSoFar = intdiv($cursorMonthIndex - $anchorMonthIndex, $rule->interval);
        $nextMonthIndex = $anchorMonthIndex + ($stepsSoFar + 1) * $rule->interval;

        $year = intdiv($nextMonthIndex, 12);
        $month = $nextMonthIndex % 12 + 1;
        $periodStart = CarbonImmutable::create($year, $month, 1);

        return [$periodStart, $this->resolveMonthOccurrence($rule, $periodStart)];
    }

    /**
     * The next anchor-aligned YEAR whose `year_month` falls strictly after
     * $cursor (spec 0155, D-1), resolved on that month exactly like a
     * monthly rule resolves its own (fixed day or ordinal weekday, one
     * level up). Unlike months (finer-grained than the anchor itself, so
     * "next month" is never the anchor's own), a year CAN still have its
     * `year_month` occurrence ahead of the anchor within the SAME aligned
     * year — an anchor of January targeting March lands in March of the
     * anchor's own year, not a whole interval later — so the aligned year
     * at $cursor's own step is tried FIRST, and only pushed one interval
     * further when that year's target month turns out to be at or before
     * $cursor (already produced, or before the anchor within its own year).
     *
     * @return array{0: CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function nextYearlyCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): array
    {
        $stepsSoFar = intdiv($cursor->year - $anchor->year, $rule->interval);
        $candidateYear = $anchor->year + $stepsSoFar * $rule->interval;
        $periodStart = CarbonImmutable::create($candidateYear, (int) $rule->yearMonth, 1);

        if (! $periodStart->gt($cursor)) {
            $candidateYear += $rule->interval;
            $periodStart = CarbonImmutable::create($candidateYear, (int) $rule->yearMonth, 1);
        }

        return [$periodStart, $this->resolveMonthOccurrence($rule, $periodStart)];
    }

    /**
     * D-2's month-end clamp (`fixed`, never carried over from a PREVIOUS
     * clamp — 31/01 -> 28/02 -> 31/03 -> 30/04, never 28/02 -> 28/03) OR
     * AC-001's ordinal weekday, which is `null` — this period produces NO
     * occurrence — when the month has no such Nth weekday at all (a "5th
     * Monday" that does not exist that month).
     */
    private function resolveMonthOccurrence(TaskRecurrenceData $rule, CarbonImmutable $firstOfMonth): ?CarbonImmutable
    {
        if ($rule->monthMode === TaskRecurrenceMonthMode::Ordinal) {
            return $this->nthWeekdayOfMonth($firstOfMonth, (int) $rule->ordinal, (int) $rule->ordinalWeekday);
        }

        $day = min((int) $rule->monthDay, $firstOfMonth->daysInMonth);

        return $firstOfMonth->setDay($day);
    }

    /**
     * The $ordinal-th occurrence (1..5) of ISO weekday $isoWeekday within
     * $firstOfMonth's own month, or null when the month does not have one —
     * a plain calendar computation (7 days per week, at most 5 possible
     * occurrences of a given weekday in any month), never a search that
     * could wander into a neighbouring month.
     */
    private function nthWeekdayOfMonth(CarbonImmutable $firstOfMonth, int $ordinal, int $isoWeekday): ?CarbonImmutable
    {
        $offsetToFirstMatch = ($isoWeekday - $firstOfMonth->dayOfWeekIso + 7) % 7;
        $target = $firstOfMonth->addDays($offsetToFirstMatch)->addWeeks($ordinal - 1);

        return $target->month === $firstOfMonth->month ? $target : null;
    }

    /**
     * Spec 0160, D-1: the first working day AT OR strictly after $date —
     * a no-op when $date is already one. Walked one day at a time: any run
     * of consecutive non-working days (a weekend abutting a fixed holiday,
     * or Pasqua/Pasquetta together) is short by construction.
     */
    private function shiftToWorkday(CarbonImmutable $date): CarbonImmutable
    {
        while ($this->calendar->isNonWorkingDay($date->toDateString())) {
            $date = $date->addDay();
        }

        return $date;
    }
}
