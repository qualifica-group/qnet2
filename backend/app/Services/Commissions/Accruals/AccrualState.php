<?php

declare(strict_types=1);

namespace App\Services\Commissions\Accruals;

use App\Models\Invoice;

/**
 * Everything the synchronizer needs for one run, loaded in bulk (no lazy loading).
 */
final readonly class AccrualState
{
    /**
     * @param  array<int, Invoice>  $invoices  affected invoices keyed by id, installments loaded
     * @param  array<int, AccrualBucket>  $buckets
     * @param  array<string, string>  $sums  bucket key => SUM(amount) of its movements
     */
    public function __construct(
        public array $invoices,
        public array $buckets,
        public array $sums,
    ) {}
}
