<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Models\CommissionSettlementNumberSequence;

/**
 * Progressive settlement number per (issuing company, year) (spec 0213, D-1).
 * Must run inside the caller's transaction: the row lock serializes concurrent
 * allocations and the counter only moves forward, so the number of a cancelled
 * settlement is never reused.
 */
final class SettlementNumberAllocator
{
    public function next(int $companyId, int $year): int
    {
        CommissionSettlementNumberSequence::query()->firstOrCreate(
            ['company_id' => $companyId, 'year' => $year],
            ['last_number' => 0],
        );

        $sequence = CommissionSettlementNumberSequence::query()
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->update(['last_number' => $sequence->last_number + 1]);

        return $sequence->last_number;
    }
}
