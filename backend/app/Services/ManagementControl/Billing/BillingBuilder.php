<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Billing;

use App\DataObjects\ManagementControl\BillingFilters;

/**
 * The billing report (spec 0271): what is left to invoice on the open commesse
 * (snapshot at today) and the life cycle of the proforma in the period. A
 * constant number of aggregate queries (the aggregators); amounts are integer
 * cents summed and leave as numbers with two decimals.
 */
final class BillingBuilder
{
    public function __construct(
        private readonly BacklogAggregator $backlog,
        private readonly ProformaCycleAggregator $proforma,
    ) {}

    /**
     * @param  string  $today  "Y-m-d", the day the snapshot refers to
     * @return array<string, mixed>
     */
    public function handle(BillingFilters $filters, string $today): array
    {
        return [
            // Step 1: the backlog of the open commesse
            'today' => $today,
            'backlog' => $this->backlog->handle($filters, $today),
            // Step 2: the proforma cycle of the period
            'proforma' => $this->proforma->handle($filters, $today),
        ];
    }
}
