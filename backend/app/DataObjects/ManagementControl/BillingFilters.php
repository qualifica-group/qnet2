<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

use Carbon\CarbonImmutable;

/**
 * Validated filters of the billing report (spec 0271). The dates are inclusive
 * and bound only the proforma cycle: the backlog is a snapshot at the current
 * day (D-1). An empty id list means "no filter"; id lists are sorted and unique
 * so equivalent requests share one cache entry.
 */
final readonly class BillingFilters
{
    public const string GROUP_BUSINESS_FUNCTION = 'business_function';

    public const string GROUP_COMPANY = 'company';

    public const string GROUP_COMMERCIAL = 'commercial';

    public const array GROUP_BY_OPTIONS = [self::GROUP_BUSINESS_FUNCTION, self::GROUP_COMPANY, self::GROUP_COMMERCIAL];

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
        public string $groupBy = self::GROUP_BUSINESS_FUNCTION,
    ) {}

    /** The day after the last day of the period: the exclusive upper bound of a timestamp comparison. */
    public function dateToExclusive(): ?string
    {
        return $this->dateTo === null ? null : CarbonImmutable::parse($this->dateTo)->addDay()->toDateString();
    }

    /**
     * The `applied` echo of the response, also the normalised cache-key input.
     *
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
            'group_by' => $this->groupBy,
        ];
    }
}
