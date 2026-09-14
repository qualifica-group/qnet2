<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\DataObjects\Tasks\TaskRecurrenceData;
use App\Enums\TaskRecurrenceEnd;
use App\Enums\TaskRecurrenceFrequency;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Pure calendar math for a recurrence rule (spec 0120, D-1/D-2/D-15): no
 * database, no implicit `now()` — every date the rule needs is a parameter,
 * which is what makes the calendar edge cases (D-2's month-end clamp, a leap
 * February) testable without a single fixture.
 *
 * `$from` doubles as BOTH the starting point of the returned dates AND the
 * alignment anchor for weekly/monthly interval math (which week/month
 * "counts" as zero). This is safe to call again and again with $from set to
 * the LAST date a previous call returned, because any date this class
 * produces is itself exactly `k * interval` weeks/months past whatever
 * anchor produced it — restarting the count from it lands on the same
 * absolute calendar as continuing from the original anchor would (the
 * pattern is self-similar from any of its own occurrences, not only the
 * first). App\Console\Commands\GenerateTaskRecurrences relies on exactly
 * this to resume from `generated_until` instead of recomputing a series'
 * whole history on every run.
 */
final class TaskRecurrenceCalculator
{
    /**
     * Defensive circuit breaker only — interval >= 1 guarantees each
     * iteration moves strictly forward, so `ends: never` with no $horizon is
     * the only way to approach this, and only if $limit is set absurdly
     * high by a caller bug.
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
            $cursor = $this->nextCandidate($rule, $from, $cursor);

            if ($horizon !== null && $cursor->gt($horizon)) {
                break;
            }

            if ($rule->ends === TaskRecurrenceEnd::OnDate && $cursor->gt(CarbonImmutable::parse($rule->endsOn))) {
                break;
            }

            if ($rule->ends === TaskRecurrenceEnd::AfterCount && $generatedCount >= $rule->occurrenceCount) {
                break;
            }

            $dates[] = $cursor;
            $generatedCount++;
        }

        return $dates;
    }

    private function nextCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): CarbonImmutable
    {
        return match ($rule->frequency) {
            TaskRecurrenceFrequency::Daily => $cursor->addDays($rule->interval),
            TaskRecurrenceFrequency::Weekly => $this->nextWeeklyCandidate($rule, $anchor, $cursor),
            TaskRecurrenceFrequency::Monthly => $this->nextMonthlyCandidate($rule, $anchor, $cursor),
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
     * The next anchor-aligned month strictly after $cursor's own month (D-2):
     * `month_day` clamped to the target month's own length, never carried
     * over from a PREVIOUS clamp — 31/01 -> 28/02 -> 31/03 -> 30/04, never
     * 28/02 -> 28/03. Month/year arithmetic only; the day-of-month is not
     * involved in deciding WHICH month is next.
     */
    private function nextMonthlyCandidate(TaskRecurrenceData $rule, CarbonImmutable $anchor, CarbonImmutable $cursor): CarbonImmutable
    {
        $anchorMonthIndex = $anchor->year * 12 + ($anchor->month - 1);
        $cursorMonthIndex = $cursor->year * 12 + ($cursor->month - 1);

        $stepsSoFar = intdiv($cursorMonthIndex - $anchorMonthIndex, $rule->interval);
        $nextMonthIndex = $anchorMonthIndex + ($stepsSoFar + 1) * $rule->interval;

        $year = intdiv($nextMonthIndex, 12);
        $month = $nextMonthIndex % 12 + 1;

        $firstOfMonth = CarbonImmutable::create($year, $month, 1);
        $day = min((int) $rule->monthDay, $firstOfMonth->daysInMonth);

        return $firstOfMonth->setDay($day);
    }
}
