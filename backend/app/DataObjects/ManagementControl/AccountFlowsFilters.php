<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

use Carbon\CarbonImmutable;

/**
 * Validated filters of the account flows report (spec 0271). The dates are
 * inclusive; a missing one leaves that side of the period open. An empty id
 * list means "no filter"; id lists are sorted and unique so equivalent
 * requests share one cache entry.
 */
final readonly class AccountFlowsFilters
{
    public const int MAX_MONTHS = 24;

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     */
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public array $companyIds = [],
        public array $companySiteIds = [],
        public array $operationalSiteIds = [],
    ) {}

    /** The day after the last day of the period: an exclusive bound, right for a date stored with a time part too. */
    public function dateToExclusive(): ?string
    {
        return $this->dateTo === null ? null : CarbonImmutable::parse($this->dateTo)->addDay()->toDateString();
    }

    /**
     * Every month between two dates, "YYYY-MM", in order.
     *
     * @return array<int, string>
     */
    public static function monthsBetween(string $from, string $to): array
    {
        $month = CarbonImmutable::parse($from)->startOfMonth();
        $last = CarbonImmutable::parse($to)->startOfMonth();
        $months = [];
        for (; $month <= $last; $month = $month->addMonthNoOverflow()) {
            $months[] = $month->format('Y-m');
        }

        return $months;
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
