<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

/**
 * Validated filters of the income statement (spec 0268). Dates are inclusive and
 * apply to `work_orders.start_date`; an empty list means "no filter". Id lists
 * are sorted and unique so equivalent requests share one cache entry.
 */
final readonly class IncomeStatementFilters
{
    public const string BASIS_CONTRACT = 'contract';

    public const string BASIS_INVOICED = 'invoiced';

    public const string BASIS_COLLECTED = 'collected';

    public const array REVENUE_BASES = [self::BASIS_CONTRACT, self::BASIS_INVOICED, self::BASIS_COLLECTED];

    public const string SEGMENT_NONE = 'none';

    public const string SEGMENT_COMPANY = 'company';

    public const string SEGMENT_COMPANY_SITE = 'company_site';

    public const string SEGMENT_OPERATIONAL_SITE = 'operational_site';

    public const string SEGMENT_WORK_ORDER_TYPE = 'work_order_type';

    public const string SEGMENT_COMMERCIAL = 'commercial';

    public const array SEGMENT_BY_OPTIONS = [
        self::SEGMENT_NONE,
        self::SEGMENT_COMPANY,
        self::SEGMENT_COMPANY_SITE,
        self::SEGMENT_OPERATIONAL_SITE,
        self::SEGMENT_WORK_ORDER_TYPE,
        self::SEGMENT_COMMERCIAL,
    ];

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     * @param  array<int, int>  $businessFunctionIds
     * @param  array<int, string>  $workOrderTypes
     * @param  array<int, int>  $commercialIds
     */
    public function __construct(
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public array $companyIds = [],
        public array $companySiteIds = [],
        public array $operationalSiteIds = [],
        public array $businessFunctionIds = [],
        public array $workOrderTypes = [],
        public array $commercialIds = [],
        public string $revenueBasis = self::BASIS_CONTRACT,
        public string $segmentBy = self::SEGMENT_NONE,
    ) {}

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
            'business_function_id' => $this->businessFunctionIds,
            'work_order_type' => $this->workOrderTypes,
            'commercial_id' => $this->commercialIds,
            'revenue_basis' => $this->revenueBasis,
            'segment_by' => $this->segmentBy,
        ];
    }
}
