<?php

declare(strict_types=1);

namespace App\Services\RequestManagement\Report;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * The report's date range (spec 0106 data_contract): inclusive on BOTH ends,
 * expressed internally as [start, endExclusive) — the exclusive upper bound
 * is the START of the day AFTER `date_to`, never an inclusive `23:59:59`
 * literal, so a row timestamped late on the last day is never cut off
 * (AC-022).
 *
 * Either bound may be open (spec 0169 D-3): a NULL side adds no constraint,
 * so "only To" reads everything up to that day and "neither" reads all-time.
 */
final class ReportDateRange
{
    public function __construct(
        public readonly ?CarbonImmutable $start,
        public readonly ?CarbonImmutable $endExclusive,
    ) {}

    public static function fromRequest(?string $dateFrom, ?string $dateTo): self
    {
        return new self(
            self::filled($dateFrom) ? CarbonImmutable::parse($dateFrom)->startOfDay() : null,
            self::filled($dateTo) ? CarbonImmutable::parse($dateTo)->startOfDay()->addDay() : null,
        );
    }

    /**
     * The ONE place the period reaches a query (spec 0169 D-3): every
     * range-bound indicator narrows `$column` through here, so an open bound
     * is skipped the same way everywhere.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function constrain(Builder $query, string $column): Builder
    {
        if ($this->start !== null) {
            $query->where($column, '>=', $this->start);
        }

        if ($this->endExclusive !== null) {
            $query->where($column, '<', $this->endExclusive);
        }

        return $query;
    }

    private static function filled(?string $date): bool
    {
        return $date !== null && $date !== '';
    }
}
