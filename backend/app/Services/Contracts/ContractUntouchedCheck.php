<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\StatusSystemKey;
use App\Models\Contract;
use App\Models\WorkOrder;

/**
 * Whether nobody has worked on a contract yet (user decision 2026-11-20,
 * spec 0072 D-3 rev.): such a contract only mirrors its won offer, so it is
 * deleted when the offer stops being won and it follows the offer to another
 * anagrafica. Read on the data, not on the activity log: a contract born in
 * qnet-2 always carries its automatic `created` entries, and a migrated one
 * carries none at all.
 *
 * Worked on = any of: validated or terminated, a status other than the one it
 * is born with (the active default, or the system "new" row), any of its own
 * data filled in, a renewal, a document, a commessa on a line of its offer.
 */
final class ContractUntouchedCheck
{
    /** The contract's own columns a user fills in through "Modifica dati". */
    private const array DATA_COLUMNS = ['renewal_date', 'expiry_date', 'payment_notes', 'comments'];

    public function __construct(private readonly ContractStatusResolver $statusResolver) {}

    public function isUntouched(Contract $contract): bool
    {
        return $this->isUnclosed($contract)
            && $this->hasBirthStatus($contract)
            && $this->hasNoData($contract)
            && $this->hasNoDependants($contract);
    }

    private function isUnclosed(Contract $contract): bool
    {
        return $contract->validated_at === null && $contract->terminated_at === null;
    }

    /** A suspended contract is judged on the status it had before the suspension. */
    private function hasBirthStatus(Contract $contract): bool
    {
        $workingStatusId = $contract->isSuspended()
            ? $contract->status_before_suspension_id
            : $contract->contract_status_id;

        $birthStatusIds = [
            $this->statusResolver->defaultActiveId(),
            $this->statusResolver->systemId(StatusSystemKey::New),
        ];

        return in_array((int) $workingStatusId, $birthStatusIds, true);
    }

    private function hasNoData(Contract $contract): bool
    {
        foreach (self::DATA_COLUMNS as $column) {
            if (filled($contract->getAttribute($column))) {
                return false;
            }
        }

        return true;
    }

    private function hasNoDependants(Contract $contract): bool
    {
        return $contract->renewals()->doesntExist()
            && $contract->attachments()->doesntExist()
            && WorkOrder::query()->withQuoteLineOfQuote($contract->quote_id)->doesntExist();
    }
}
