<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

use Carbon\CarbonImmutable;

/**
 * Validated filters of the monthly trend (spec 0270): the period is by document
 * date and only its months matter. An empty id list means "no filter"; id lists
 * are sorted and unique so equivalent requests share one cache entry.
 */
final readonly class MonthlyTrendFilters
{
    public const int MAX_MONTHS = 24;

    /** Months back of the comparison period (previous year). */
    public const int PREVIOUS_YEAR_SHIFT = 12;

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     */
    public function __construct(
        public string $dateFrom,
        public string $dateTo,
        public array $companyIds = [],
        public array $companySiteIds = [],
        public array $operationalSiteIds = [],
    ) {}

    /**
     * Every month of the interval, "YYYY-MM", in order.
     *
     * @return array<int, string>
     */
    public function months(): array
    {
        $month = CarbonImmutable::parse($this->dateFrom)->startOfMonth();
        $last = CarbonImmutable::parse($this->dateTo)->startOfMonth();
        $months = [];
        for (; $month <= $last; $month = $month->addMonthNoOverflow()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
    }

    /**
     * The dates covering the interval and its previous-year counterpart: the first one
     * inclusive, the last one exclusive (the day after the interval, so a date stored
     * with a time part at midnight is still inside).
     *
     * @return array{0: string, 1: string}
     */
    public function queryRange(): array
    {
        $from = CarbonImmutable::parse($this->dateFrom)->startOfMonth()->subMonthsNoOverflow(self::PREVIOUS_YEAR_SHIFT);

        return [$from->toDateString(), CarbonImmutable::parse($this->dateTo)->startOfMonth()->addMonthNoOverflow()->toDateString()];
    }

    /** The same month one year earlier, "YYYY-MM". */
    public static function previousYear(string $month): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $month)->subMonthsNoOverflow(self::PREVIOUS_YEAR_SHIFT)->format('Y-m');
    }

    /**
     * @return array<string, mixed>
     */
    public function applied(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'company_id' => $this->companyIds,
            'company_site_id' => $this->companySiteIds,
            'operational_site_id' => $this->operationalSiteIds,
        ];
    }
}
