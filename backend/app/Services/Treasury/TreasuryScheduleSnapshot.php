<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\DataObjects\ManagementControl\ExposureFilters;
use App\Services\ManagementControl\Exposure\AgingSql;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\Exposure\ScheduleAggregator;
use Carbon\CarbonImmutable;

/**
 * One side of the schedule for the treasury overview (spec 0362): the open
 * residual at today with the overdue buckets, and the residual falling due in
 * each forecast window (D-2: today to today+N inclusive, overdue excluded).
 * Reuses the exposure scope and aging SQL; one query per call, amounts in cents.
 */
final class TreasuryScheduleSnapshot
{
    /** Overdue bucket keys of `AgingSql::BUCKETS` shown by the overview. */
    public const array OVERDUE_BUCKETS = ['overdue_1_30', 'overdue_31_60', 'overdue_61_90', 'overdue_over_90'];

    /** Forecast windows in days, in the contract order. */
    public const array FORECAST_DAYS = [30, 60, 90];

    /**
     * @return array{open: int, overdue: array<string, int>}
     */
    public function aging(ScheduleAggregator $side, ?int $companyId, string $today): array
    {
        $columns = $side->columns();
        [$select, $bindings] = AgingSql::buckets($columns['due'], $columns['residual'], $today);
        [$totals, $totalBindings] = AgingSql::totals($columns['due'], $columns['residual'], $today);
        $row = $side->scopedInstallments($this->filters($companyId))
            ->selectRaw("{$totals}, {$select}", [...$totalBindings, ...$bindings])
            ->first();

        $overdue = [];
        foreach (self::OVERDUE_BUCKETS as $bucket) {
            $overdue[$bucket] = ExposureSupport::cents($row->{AgingSql::BUCKET_PREFIX.$bucket} ?? 0);
        }

        return ['open' => ExposureSupport::cents($row->open_total ?? 0), 'overdue' => $overdue];
    }

    /**
     * Residual cents falling due from today to today+N, by N.
     *
     * @return array<int, int>
     */
    public function forecast(ScheduleAggregator $side, ?int $companyId, string $today): array
    {
        $columns = $side->columns();
        [$due, $residual] = [$columns['due'], $columns['residual']];
        $selects = [];
        $bindings = [];
        foreach (self::FORECAST_DAYS as $days) {
            $selects[] = "SUM(CASE WHEN {$residual} > 0 AND {$due} >= ? AND {$due} < ? THEN {$residual} ELSE 0 END) AS window_{$days}";
            // Exclusive day-after bound: due dates may carry a time part.
            array_push($bindings, $today, self::until($today, $days + 1));
        }
        $row = $side->scopedInstallments($this->filters($companyId))->selectRaw(implode(', ', $selects), $bindings)->first();

        $cents = [];
        foreach (self::FORECAST_DAYS as $days) {
            $cents[$days] = ExposureSupport::cents($row->{"window_{$days}"} ?? 0);
        }

        return $cents;
    }

    public static function until(string $today, int $days): string
    {
        return CarbonImmutable::parse($today)->addDays($days)->toDateString();
    }

    /** Invoices and proformas of the company, or of all (D-3). */
    private function filters(?int $companyId): ExposureFilters
    {
        return new ExposureFilters(
            companyIds: $companyId === null ? [] : [$companyId],
            documentType: ExposureFilters::TYPE_ALL,
        );
    }
}
