<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\QuoteStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteStatus;

/**
 * The Quote -> Contract lifecycle automation (spec 0072, BR-1), called
 * INSIDE QuoteService::create()/update()'s own transaction right after the
 * quote's status has been written, comparing its QuoteStatusGroup BEFORE and
 * AFTER that write:
 *
 *  - non-closed_won -> closed_won: creates the `contracts` row (idempotent —
 *    AC-002/003: a second transition into closed_won is a no-op if one
 *    already exists), `accepted_at` = today (D-7, written ONCE here), status
 *    = the active `is_default` row (fallback: the system 'new' row),
 *    activity `contract.created`.
 *  - closed_won -> non-closed_won: if the contract exists and is not
 *    ALREADY suspended, saves the current status as
 *    `status_before_suspension_id`, moves to the system 'suspended' row,
 *    stamps `suspended_at`, activity `contract.suspended`. Never deletes
 *    anything (D-3).
 *  - any other transition (closed_won -> closed_won, or no group change at
 *    all): no effect.
 */
class ContractLifecycleManager
{
    public function __construct(private readonly ContractStatusResolver $statusResolver) {}

    /**
     * @param  int|null  $previousStatusId  the quote's `quote_status_id`
     *                                      BEFORE this write — null on create (a fresh quote never had a prior
     *                                      group, AC-001/003).
     */
    public function syncOnStatusChange(Quote $quote, ?int $previousStatusId): void
    {
        $before = $this->groupOf($previousStatusId);
        $after = $this->groupOf($quote->quote_status_id);

        if ($before !== QuoteStatusGroup::ClosedWon && $after === QuoteStatusGroup::ClosedWon) {
            $this->createContract($quote);

            return;
        }

        if ($before === QuoteStatusGroup::ClosedWon && $after !== QuoteStatusGroup::ClosedWon) {
            $this->suspendContract($quote);
        }
    }

    private function groupOf(?int $quoteStatusId): ?QuoteStatusGroup
    {
        return $quoteStatusId === null ? null : QuoteStatus::find($quoteStatusId)?->group;
    }

    private function createContract(Quote $quote): void
    {
        if (Contract::where('quote_id', $quote->id)->exists()) {
            return; // AC-002: already a contract for this quote, no-op.
        }

        // `quote_id`/`accepted_at` are outside #[Fillable] (D-6/D-7): direct
        // assignment, mirroring Quote::code's own assignment in
        // QuoteService::create().
        $contract = new Contract(['contract_status_id' => $this->statusResolver->defaultActiveId()]);
        $contract->quote_id = $quote->id;
        $contract->accepted_at = now()->toDateString();
        $contract->save();

        activity($contract->getTable())->performedOn($contract)->event('contract.created')->log('Contract created');
    }

    private function suspendContract(Quote $quote): void
    {
        $contract = Contract::where('quote_id', $quote->id)->first();

        if ($contract === null || $contract->isSuspended()) {
            return;
        }

        $contract->forceFill([
            'status_before_suspension_id' => $contract->contract_status_id,
            'contract_status_id' => $this->statusResolver->systemId(StatusSystemKey::Suspended),
            'suspended_at' => now(),
        ])->save();

        activity($contract->getTable())->performedOn($contract)->event('contract.suspended')->log('Contract suspended');
    }
}
