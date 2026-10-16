<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

/**
 * Validated filters of the schedule and exposure report (spec 0269). The dates
 * are inclusive and bound only the flows (settled, invoiced, average days,
 * concentration): the open schedule, the aging and the commitments are a
 * snapshot at the current day (D-1). An empty list means "no filter"; id lists
 * are sorted and unique so equivalent requests share one cache entry.
 */
final readonly class ExposureFilters
{
    public const string TYPE_ALL = 'all';

    public const string TYPE_PROFORMA = 'proforma';

    public const string TYPE_INVOICE = 'invoice';

    public const array DOCUMENT_TYPES = [self::TYPE_ALL, self::TYPE_PROFORMA, self::TYPE_INVOICE];

    public const string GROUP_COMPANY = 'company';

    public const string GROUP_COMPANY_SITE = 'company_site';

    public const string GROUP_OPERATIONAL_SITE = 'operational_site';

    public const array GROUP_BY_OPTIONS = [self::GROUP_COMPANY, self::GROUP_COMPANY_SITE, self::GROUP_OPERATIONAL_SITE];

    public const int TOP_DEFAULT = 10;

    public const int TOP_MAX = 25;

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
        public string $documentType = self::TYPE_INVOICE,
        public string $groupBy = self::GROUP_COMPANY,
        public int $top = self::TOP_DEFAULT,
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
            'document_type' => $this->documentType,
            'group_by' => $this->groupBy,
            'top' => $this->top,
        ];
    }
}
