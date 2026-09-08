<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Carbon\CarbonImmutable;

/**
 * The report's date range (spec 0106 data_contract): inclusive on BOTH ends,
 * expressed internally as [start, endExclusive) — the exclusive upper bound
 * is the START of the day AFTER `date_to`, never an inclusive `23:59:59`
 * literal, so a row timestamped late on the last day is never cut off
 * (AC-022).
 */
final class ReportDateRange
{
    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $endExclusive,
    ) {}

    public static function fromRequest(string $dateFrom, string $dateTo): self
    {
        return new self(
            CarbonImmutable::parse($dateFrom)->startOfDay(),
            CarbonImmutable::parse($dateTo)->startOfDay()->addDay(),
        );
    }
}
