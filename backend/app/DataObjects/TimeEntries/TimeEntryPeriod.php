<?php

declare(strict_types=1);

namespace App\DataObjects\TimeEntries;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;

/**
 * A resolved `[dateFrom, dateTo]` range (spec 0122, data_contract F —
 * "Periodo"), both bounds inclusive. Built by `TimeEntryPeriodResolver`,
 * never validated here: the max-range check (422, AC-015) already ran in
 * `ValidatesTimeEntryFilters` before this object exists.
 */
final readonly class TimeEntryPeriod
{
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
    ) {}

    /**
     * Every calendar date in the range, ascending — the ONE DaySummary per
     * date rule (data_contract GET /api/time-entries).
     *
     * @return list<string>
     */
    public function dates(): array
    {
        return array_map(
            static fn (CarbonInterface $date): string => $date->format('Y-m-d'),
            iterator_to_array(CarbonPeriod::create($this->dateFrom, $this->dateTo)),
        );
    }
}
