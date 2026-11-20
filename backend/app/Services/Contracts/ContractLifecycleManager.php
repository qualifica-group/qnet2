<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\StatusSystemKey;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\Quote;
use App\Models\QuoteWorkflowStatus;
use App\Services\Notifications\TeamActivityNotifier;

/**
 * The Quote -> Contract lifecycle automation (spec 0072, BR-1; re-targeted
 * at the offer's workflow status by spec 0083), called INSIDE
 * QuoteService::create()/update()'s own transaction right after the quote's
 * status has been written, comparing its WorkflowStatusGroup BEFORE and
 * AFTER that write:
 *
 *  - non-closed_won -> closed_won: creates the `contracts` row (idempotent —
 *    AC-002/003: a second transition into closed_won is a no-op if one
 *    already exists), `accepted_at` = today (D-7, written ONCE here), status
 *    = the active `is_default` row (fallback: the system 'new' row),
 *    activity `contract.created`. Spec 0091: nothing is created at all when
 *    the opportunity's product categories say the branch is not sold under a
 *    contract (ContractEligibility).
 *  - closed_won -> non-closed_won (D-3 rev., user decision 2026-11-20): a
 *    contract nobody has worked on yet (ContractUntouchedCheck) is deleted,
 *    since an offer that is no longer won has no contract; the offer keeps an
 *    activity `contract.removed`. A worked-on contract that is not ALREADY
 *    suspended saves the current status as `status_before_suspension_id`,
 *    moves to the system 'suspended' row, stamps `suspended_at`, activity
 *    `contract.suspended`.
 *  - any other transition (closed_won -> closed_won, or no group change at
 *    all): no effect.
 */
class ContractLifecycleManager
{
    /** Activity on the offer when its untouched contract is deleted. */
    public const string CONTRACT_REMOVED_EVENT = 'contract.removed';

    public function __construct(
        private readonly ContractStatusResolver $statusResolver,
        private readonly ContractEligibility $eligibility,
        private readonly ContractUntouchedCheck $untouchedCheck,
        private readonly TeamActivityNotifier $teamActivity,
    ) {}

    /**
     * @param  int|null  $previousStatusId  the quote's `quote_workflow_status_id`
     *                                      BEFORE this write — null on create (a fresh quote never had a prior
     *                                      group, AC-001/003).
     */
    public function syncOnStatusChange(Quote $quote, ?int $previousStatusId): void
    {
        // Spec 0413: every writer of the offer's status passes here, so the
        // actor's managers hear of a new offer or a status move from here too.
        if ($previousStatusId === null && $quote->wasRecentlyCreated) {
            $this->teamActivity->created($quote);
        } else {
            $this->teamActivity->statusChanged($quote, $previousStatusId);
        }

        $before = $this->groupOf($previousStatusId);
        $after = $this->groupOf($quote->quote_workflow_status_id);

        if ($before !== WorkflowStatusGroup::ClosedWon && $after === WorkflowStatusGroup::ClosedWon) {
            $this->createContract($quote);

            return;
        }

        if ($before === WorkflowStatusGroup::ClosedWon && $after !== WorkflowStatusGroup::ClosedWon) {
            $this->withdrawContract($quote);
        }
    }

    private function groupOf(?int $quoteWorkflowStatusId): ?WorkflowStatusGroup
    {
        return $quoteWorkflowStatusId === null ? null : QuoteWorkflowStatus::find($quoteWorkflowStatusId)?->group;
    }

    private function createContract(Quote $quote): void
    {
        if (Contract::where('quote_id', $quote->id)->exists()) {
            return; // AC-002: already a contract for this quote, no-op.
        }

        // Spec 0091 AC-009: the branch is not sold under a contract. Checked
        // AFTER the idempotence guard (INV-3) so a pre-existing contract is
        // never re-evaluated against a flag flipped later.
        if (! $this->eligibility->allowsContract($quote)) {
            return;
        }

        // `quote_id`/`accepted_at` are outside #[Fillable] (D-6/D-7): direct
        // assignment, mirroring Quote::code's own assignment in
        // QuoteService::create().
        $contract = new Contract(['contract_status_id' => $this->statusResolver->defaultActiveId()]);
        $contract->quote_id = $quote->id;
        $contract->accepted_at = now()->toDateString();
        $contract->save();

        activity($contract->getTable())->performedOn($contract)->event('contract.created')->log('Contract created');
        $this->teamActivity->created($contract);
    }

    private function withdrawContract(Quote $quote): void
    {
        $contract = Contract::where('quote_id', $quote->id)->first();

        if ($contract === null) {
            return;
        }

        $this->untouchedCheck->isUntouched($contract)
            ? $this->deleteContract($quote, $contract)
            : $this->suspendContract($contract);
    }

    /**
     * Logged on the offer: the contract's own log goes with it. Public for
     * the one-off cleanup of the contracts suspended before D-3 rev.
     * (RemoveUntouchedSuspendedContracts).
     */
    public function deleteContract(Quote $quote, Contract $contract): void
    {
        $contract->delete();

        activity($quote->getTable())
            ->performedOn($quote)
            ->event(self::CONTRACT_REMOVED_EVENT)
            ->withProperties(['contract_id' => $contract->id, 'accepted_at' => $contract->accepted_at?->toDateString()])
            ->log('Contract removed: the offer is no longer won');
    }

    private function suspendContract(Contract $contract): void
    {
        if ($contract->isSuspended()) {
            return;
        }

        $previousStatusId = $contract->contract_status_id;

        $contract->forceFill([
            'status_before_suspension_id' => $contract->contract_status_id,
            'contract_status_id' => $this->statusResolver->systemId(StatusSystemKey::Suspended),
            'suspended_at' => now(),
        ])->save();

        activity($contract->getTable())->performedOn($contract)->event('contract.suspended')->log('Contract suspended');
        $this->teamActivity->statusChanged($contract, $previousStatusId);
    }
}
