<?php

declare(strict_types=1);

namespace App\DataObjects\Ledger;

use Carbon\CarbonImmutable;

/**
 * Validated filters of the registry ledger (spec 0266). Dates are inclusive
 * and apply to the document date; an empty id list means "no filter".
 */
final readonly class LedgerFilters
{
    public const string TYPE_ALL = 'all';

    public const string TYPE_PROFORMA = 'proforma';

    public const string TYPE_INVOICE = 'invoice';

    /**
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     */
    public function __construct(
        public string $documentType = self::TYPE_INVOICE,
        public ?CarbonImmutable $dateFrom = null,
        public ?CarbonImmutable $dateTo = null,
        public array $companyIds = [],
        public array $companySiteIds = [],
        public array $operationalSiteIds = [],
    ) {}

    public function hasSiteFilter(): bool
    {
        return $this->companySiteIds !== [] || $this->operationalSiteIds !== [];
    }
}
