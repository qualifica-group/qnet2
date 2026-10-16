<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

use Carbon\CarbonImmutable;

/**
 * Validated filters of the agents report (spec 0272, D-5). The dates are
 * inclusive and bound the flows (accrued, paid); a missing one leaves that side
 * of the period open. An empty company list means "no filter"; the list is
 * sorted and unique so equivalent requests share one cache entry.
 */
final readonly class AgentsFilters
{
    public const int MAX_MONTHS = 24;

    public const int TOP_DEFAULT = 20;

    public const int TOP_MAX = 50;

    /**
     * @param  array<int, int>  $companyIds
     */
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public array $companyIds = [],
        public int $top = self::TOP_DEFAULT,
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
            'top' => $this->top,
        ];
    }
}
