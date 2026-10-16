<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

/**
 * Validated filters of the structure costs (spec 0268). Dates are inclusive and
 * apply to `purchase_orders.order_date` / `purchase_invoices.document_date`; an
 * empty list means "no filter".
 */
final readonly class StructureCostsFilters
{
    public const string GROUP_COMPANY = 'company';

    public const string GROUP_COMPANY_SITE = 'company_site';

    public const string GROUP_OPERATIONAL_SITE = 'operational_site';

    public const array GROUP_BY_OPTIONS = [self::GROUP_COMPANY, self::GROUP_COMPANY_SITE, self::GROUP_OPERATIONAL_SITE];

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
        public string $groupBy = self::GROUP_COMPANY,
    ) {}

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
            'group_by' => $this->groupBy,
        ];
    }
}
