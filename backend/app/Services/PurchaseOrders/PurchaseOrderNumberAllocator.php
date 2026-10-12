<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseOrderNumberSequence;

/**
 * Progressive order number per (company, year of the order date) (spec 0210,
 * D-11). Must run inside the caller's transaction: the row lock serializes
 * concurrent allocations, and the counter only moves forward so a deleted
 * number is never reused.
 */
final class PurchaseOrderNumberAllocator
{
    public function next(int $companyId, int $year): int
    {
        PurchaseOrderNumberSequence::query()->firstOrCreate(
            ['company_id' => $companyId, 'year' => $year],
            ['last_number' => 0],
        );

        $sequence = PurchaseOrderNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->update(['last_number' => $sequence->last_number + 1]);

        return $sequence->last_number;
    }
}
