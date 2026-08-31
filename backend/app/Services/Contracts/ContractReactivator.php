<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\DataObjects\Contracts\ReactivateContractData;
use App\Enums\ContractStatusGroup;
use App\Enums\WorkflowStatusGroup;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Services\ContractService;
use Illuminate\Support\Facades\DB;

/**
 * "Riattiva contratto" — the only action with TWO paths, split out of
 * ContractActionService (engineering.md §6: that class crossed the 300-line
 * soft limit once the directive of 2026-08-31 grew it):
 *
 * - SUSPENDED (BR-2/D-3): allowed only while the linked quote is CURRENTLY
 *   back in a closed_won group; restores the pre-suspension status, falling
 *   back to the active `is_default` row if that one has since been
 *   deactivated. No payload.
 * - CLOSED_LOST (user directive 2026-08-31): allowed whatever the quote's
 *   current status — the disdetta is a commercial decision of its own — and
 *   the destination status comes from the client, since nothing ever
 *   recorded the one preceding the closure. Clears the whole termination
 *   stamp so the contract stops being disdetto.
 *
 * A contract that is neither is refused (422). The negative closure is read
 * on the status GROUP, not on the `terminated_at` stamp: "Annullato" closes
 * a contract just as "Disdetto" does, and both leave through here.
 */
class ContractReactivator
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ContractStatusResolver $statusResolver,
    ) {}

    public function reactivate(Contract $contract, ReactivateContractData $data): Contract
    {
        DB::transaction(function () use ($contract, $data): void {
            $this->assertReactivatable($contract);

            $this->isClosedLost($contract)
                ? $this->reactivateClosed($contract, $data)
                : $this->reactivateSuspended($contract);
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    private function reactivateSuspended(Contract $contract): void
    {
        $this->assertQuoteClosedWon($contract);

        $restoredStatusId = $this->restoredStatusId($contract);

        $contract->contract_status_id = $restoredStatusId;
        $contract->status_before_suspension_id = null;
        $contract->suspended_at = null;
        $contract->save();

        $this->logReactivation($contract, $restoredStatusId, 'suspended');
    }

    private function reactivateClosed(Contract $contract, ReactivateContractData $data): void
    {
        $statusId = $this->submittedStatusId($data);

        $contract->contract_status_id = $statusId;
        $contract->terminated_at = null;
        $contract->termination_reason = null;
        $contract->terminated_by = null;
        $contract->save();

        $this->logReactivation($contract, $statusId, 'terminated');
    }

    private function logReactivation(Contract $contract, int $statusId, string $from): void
    {
        activity($contract->getTable())
            ->performedOn($contract)
            ->event('contract.reactivated')
            ->withProperties(['contract_status_id' => $statusId, 'reactivated_from' => $from])
            ->log('Contract reactivated');
    }

    /**
     * The destination status of a closed contract's reactivation.
     * ReactivateContractRequest already makes it mandatory on this path and
     * restricts it to the open/pending groups; re-asserted here since this
     * method may be invoked directly by any future caller.
     */
    private function submittedStatusId(ReactivateContractData $data): int
    {
        if (! $data->contractStatusIdSubmitted || $data->contractStatusId === null) {
            abort(422, 'A destination status is required to reactivate a closed contract.');
        }

        $group = ContractStatus::query()->whereKey($data->contractStatusId)->value('group');

        if (! in_array($group, [ContractStatusGroup::Open, ContractStatusGroup::Pending], true)) {
            abort(422, 'The destination status must belong to the open or pending group.');
        }

        return $data->contractStatusId;
    }

    private function restoredStatusId(Contract $contract): int
    {
        $previousId = $contract->status_before_suspension_id;

        if ($previousId !== null && ContractStatus::query()->whereKey($previousId)->where('is_active', true)->exists()) {
            return $previousId;
        }

        return $this->statusResolver->defaultActiveId();
    }

    private function assertReactivatable(Contract $contract): void
    {
        if (! $contract->isSuspended() && ! $this->isClosedLost($contract)) {
            abort(422, 'This contract is neither suspended nor closed.');
        }
    }

    private function isClosedLost(Contract $contract): bool
    {
        $contract->loadMissing('contractStatus');

        return $contract->contractStatus?->group === ContractStatusGroup::ClosedLost;
    }

    private function assertQuoteClosedWon(Contract $contract): void
    {
        $contract->loadMissing('quote.quoteWorkflowStatus');

        if ($contract->quote->quoteWorkflowStatus?->group !== WorkflowStatusGroup::ClosedWon) {
            abort(422, 'The linked quote is not currently closed_won.');
        }
    }
}
