<?php

declare(strict_types=1);

namespace App\Services\TimeEntries;

use App\DataObjects\TimeEntries\TimeEntryFilterData;
use App\DataObjects\TimeEntries\TimeEntryPeriod;
use Carbon\CarbonImmutable;

/**
 * F's "Periodo" resolution (spec 0122, data_contract): `date_from`/
 * `date_to` win outright when EITHER is present; otherwise `period_preset`
 * relative to today; otherwise the current week. ISO weeks (Monday..Sunday).
 *
 * Two ambiguous cases the spec leaves to this implementation, both
 * documented in `TimeEntryPeriodResolverTest`:
 *   - Only ONE of `date_from`/`date_to` submitted: the period collapses to
 *     that single day (the missing bound takes the given one's value)
 *     rather than falling back to a preset — an explicit date always wins
 *     over an implicit default.
 *   - "Today" for the preset math is `Europe/Rome`, matching D-7's own
 *     Easter/holiday timezone (the app's own default is UTC, which is
 *     exactly the mismatch D-7 calls out as qnet's bug).
 */
final class TimeEntryPeriodResolver
{
    private const string TIMEZONE = 'Europe/Rome';

    public function resolve(TimeEntryFilterData $filter): TimeEntryPeriod
    {
        if ($filter->dateFrom !== null || $filter->dateTo !== null) {
            $from = $filter->dateFrom ?? $filter->dateTo;
            $to = $filter->dateTo ?? $filter->dateFrom;

            return new TimeEntryPeriod($from, $to);
        }

        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();

        return match ($filter->periodPreset) {
            'day' => new TimeEntryPeriod($today->format('Y-m-d'), $today->format('Y-m-d')),
            'month' => new TimeEntryPeriod($today->startOfMonth()->format('Y-m-d'), $today->endOfMonth()->format('Y-m-d')),
            'year' => new TimeEntryPeriod($today->startOfYear()->format('Y-m-d'), $today->endOfYear()->format('Y-m-d')),
            default => new TimeEntryPeriod(
                $today->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d'),
                $today->endOfWeek(CarbonImmutable::SUNDAY)->format('Y-m-d'),
            ),
        };
    }
}
