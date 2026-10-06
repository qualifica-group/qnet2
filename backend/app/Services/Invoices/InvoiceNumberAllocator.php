<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\InvoiceNumberSequence;

/**
 * Progressive document number per (issuing company, year) (spec 0194, D-6).
 * Must run inside the caller's transaction: the row lock serializes concurrent
 * allocations, and the counter only moves forward so a deleted number is never reused.
 */
final class InvoiceNumberAllocator
{
    public function next(int $companyId, int $year): int
    {
        InvoiceNumberSequence::query()->firstOrCreate(
            ['company_id' => $companyId, 'year' => $year],
            ['last_number' => 0],
        );

        $sequence = InvoiceNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->update(['last_number' => $sequence->last_number + 1]);

        return $sequence->last_number;
    }
}
