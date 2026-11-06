<?php

declare(strict_types=1);

use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\QuoteLine;
use App\Models\WorkOrder;

if (! function_exists('renewContractTo')) {
    /**
     * Brings $contract to the renewal period $number by creating the missing
     * `contract_renewals` rows (1..$number), one year after the other. Writes
     * the rows only: the contract dates are left to the caller (spec 0358).
     */
    function renewContractTo(Contract $contract, int $number): Contract
    {
        $existing = (int) $contract->renewals()->reorder()->max('number');

        for ($n = $existing + 1; $n <= $number; $n++) {
            ContractRenewal::factory()->create([
                'contract_id' => $contract->id,
                'number' => $n,
                'starts_on' => (2026 + $n).'-01-01',
                'previous_expiry_date' => (2025 + $n).'-12-31',
                'expiry_date' => (2026 + $n).'-12-31',
            ]);
        }

        return $contract->unsetRelation('renewals');
    }
}

if (! function_exists('attachLineInPeriod')) {
    /**
     * Programs $line into $workOrder within the renewal period $period
     * (pivot `renewal_number`, spec 0358).
     */
    function attachLineInPeriod(WorkOrder $workOrder, QuoteLine $line, int $period): void
    {
        $workOrder->quoteLines()->attach($line->id, ['renewal_number' => $period]);
    }
}
