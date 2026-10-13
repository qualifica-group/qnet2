<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\CommissionSettlementSkipReason;
use RuntimeException;

/**
 * A pair cannot be settled (no profile, no ENASARCO parameters). The runner
 * turns it into a skipped entry, the recalculation into a 422 with the reason.
 */
final class SettlementSkippedException extends RuntimeException
{
    public function __construct(public readonly CommissionSettlementSkipReason $reason)
    {
        parent::__construct($reason->value);
    }
}
