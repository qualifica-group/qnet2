<?php

declare(strict_types=1);

namespace App\DataObjects\ManagementControl;

/**
 * Validated filters of the cash flow forecast (spec 0270). The report is a
 * snapshot at the current day projected over `horizonMonths` months from the
 * current one. An empty id list means "no filter"; id lists are sorted and
 * unique so equivalent requests share one cache entry.
 */
final readonly class CashFlowFilters
{
    public const int HORIZON_DEFAULT = 6;

    public const int HORIZON_MAX = 24;

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     */
    public function __construct(
        public array $companyIds = [],
        public array $companySiteIds = [],
        public array $operationalSiteIds = [],
        public string $documentType = ExposureFilters::TYPE_ALL,
        public int $horizonMonths = self::HORIZON_DEFAULT,
    ) {}

    /**
     * The `applied` echo of the response, also the normalised cache-key input.
     *
     * @return array<string, mixed>
     */
    public function applied(): array
    {
        return [
            'company_id' => $this->companyIds,
            'company_site_id' => $this->companySiteIds,
            'operational_site_id' => $this->operationalSiteIds,
            'document_type' => $this->documentType,
            'horizon_months' => $this->horizonMonths,
        ];
    }
}
