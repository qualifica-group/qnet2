<?php

declare(strict_types=1);

namespace App\Services\Quotes;

use App\Models\Quote;
use App\Services\Contracts\ContractUntouchedCheck;

/**
 * Whether an offer may still move (user decisions 2026-11-20): to another
 * anagrafica ("Trasferisci ad altra anagrafica") or to another Sede
 * ("Trasferisci contatto"). One rule for every button and endpoint: no
 * contract, or a contract nobody has worked on yet (ContractUntouchedCheck),
 * which follows the offer since it has no anagrafica nor Sede of its own.
 *
 * The contact transfer has one more opening: a contract not validated yet
 * whose category still owes the transfer (ContactTransferBeforeContractRule)
 * keeps it, or filling in a date would leave a contract that can be neither
 * transferred nor validated.
 */
final class QuoteTransferAvailability
{
    public function __construct(
        private readonly ContractUntouchedCheck $untouchedCheck,
        private readonly ContactTransferBeforeContractRule $contactTransferRule,
    ) {}

    public function allowsRegistryTransfer(Quote $quote): bool
    {
        $contract = $quote->contract()->first();

        return $contract === null || $this->untouchedCheck->isUntouched($contract);
    }

    public function allowsContactTransfer(Quote $quote): bool
    {
        $contract = $quote->contract()->first();

        if ($contract === null || $this->untouchedCheck->isUntouched($contract)) {
            return true;
        }

        return $contract->validated_at === null && $this->contactTransferRule->owesTransfer($quote);
    }
}
