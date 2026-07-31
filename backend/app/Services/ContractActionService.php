<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Contracts\ScheduleContractData;
use App\DataObjects\Contracts\TerminateContractData;
use App\DataObjects\Contracts\ValidateContractData;
use App\Enums\ContractStatusGroup;
use App\Enums\QuoteStatusGroup;
use App\Enums\StatusSystemKey;
use App\Models\Contract;
use App\Models\ContractStatus;
use App\Models\User;
use App\Services\Contracts\ContractStatusResolver;
use Illuminate\Support\Facades\DB;

/**
 * The 4 domain actions on a contract (spec 0072, BR-2/3/4): validate,
 * schedule, terminate, reactivate. Each is a single, small write inside its
 * own transaction, followed by the SAME detail read ContractController::show
 * uses (ContractService::loadDetail()), so every action's response is
 * identical in shape to a plain GET.
 */
class ContractActionService
{
    public function __construct(
        private readonly ContractService $contractService,
        private readonly ContractStatusResolver $statusResolver,
    ) {}

    /**
     * BR-3: not repeatable (422 if already validated), refused while
     * suspended (422) — a suspended contract has no working status to
     * validate into.
     */
    public function validate(Contract $contract, ValidateContractData $data, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $data, $actor): void {
            $this->assertNotAlreadyValidated($contract);
            $this->assertNotSuspended($contract);

            $validatedAt = $data->validatedAtSubmitted ? $data->validatedAt : now()->toDateString();

            $contract->validated_at = $validatedAt;
            $contract->validated_by = $actor->id;

            if ($data->contractStatusIdSubmitted) {
                $contract->contract_status_id = $data->contractStatusId;
            }

            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->causedBy($actor)
                ->event('contract.validated')
                ->withProperties([
                    'validated_at' => $validatedAt,
                    'validated_by' => $actor->id,
                    'contract_status_id' => $contract->contract_status_id,
                ])
                ->log('Contract validated');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * D-2: "Programmato"/"Da programmare" are plain, deletable custom rows —
     * the destination status ALWAYS comes from the client here, never
     * resolved by system_key.
     */
    public function schedule(Contract $contract, ScheduleContractData $data): Contract
    {
        DB::transaction(function () use ($contract, $data): void {
            $this->assertNotSuspended($contract);
            $this->assertNotTerminated($contract);

            $contract->expiry_date = $data->expiryDate;
            $contract->renewal_date = $data->renewalDate;
            $contract->contract_status_id = $data->contractStatusId;
            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->event('contract.scheduled')
                ->withProperties([
                    'expiry_date' => $data->expiryDate,
                    'renewal_date' => $data->renewalDate,
                    'contract_status_id' => $data->contractStatusId,
                ])
                ->log('Contract scheduled');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * BR-4: not repeatable (422 if already disdetto). Default destination is
     * the system 'terminated' row (D-2); a client-provided one MUST belong
     * to the closed_lost group — enforced by TerminateContractRequest's
     * Rule::exists already, re-asserted here in depth since this method may
     * be invoked directly by any future caller.
     */
    public function terminate(Contract $contract, TerminateContractData $data, User $actor): Contract
    {
        DB::transaction(function () use ($contract, $data, $actor): void {
            $this->assertNotTerminated($contract);

            $statusId = $data->contractStatusIdSubmitted
                ? $data->contractStatusId
                : $this->statusResolver->systemId(StatusSystemKey::Terminated);

            $this->assertClosedLost($statusId);

            $contract->terminated_at = $data->terminatedAt;
            $contract->termination_reason = $data->terminationReason;
            $contract->terminated_by = $actor->id;
            $contract->contract_status_id = $statusId;
            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->causedBy($actor)
                ->event('contract.terminated')
                ->withProperties([
                    'terminated_at' => $data->terminatedAt,
                    'termination_reason' => $data->terminationReason,
                    'terminated_by' => $actor->id,
                    'contract_status_id' => $statusId,
                ])
                ->log('Contract terminated');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    /**
     * BR-2/D-3: allowed only while suspended AND the linked quote is
     * CURRENTLY back in a closed_won group. Restores the pre-suspension
     * status, falling back to the active `is_default` row if that one has
     * since been deactivated.
     */
    public function reactivate(Contract $contract): Contract
    {
        DB::transaction(function () use ($contract): void {
            $this->assertSuspended($contract);
            $this->assertQuoteClosedWon($contract);

            $restoredStatusId = $this->restoredStatusId($contract);

            $contract->contract_status_id = $restoredStatusId;
            $contract->status_before_suspension_id = null;
            $contract->suspended_at = null;
            $contract->save();

            activity($contract->getTable())
                ->performedOn($contract)
                ->event('contract.reactivated')
                ->withProperties(['contract_status_id' => $restoredStatusId])
                ->log('Contract reactivated');
        });

        return $this->contractService->loadDetail($contract->fresh());
    }

    private function restoredStatusId(Contract $contract): int
    {
        $previousId = $contract->status_before_suspension_id;

        if ($previousId !== null && ContractStatus::query()->whereKey($previousId)->where('is_active', true)->exists()) {
            return $previousId;
        }

        return $this->statusResolver->defaultActiveId();
    }

    private function assertNotAlreadyValidated(Contract $contract): void
    {
        if ($contract->validated_at !== null) {
            abort(422, 'This contract has already been validated.');
        }
    }

    private function assertNotSuspended(Contract $contract): void
    {
        if ($contract->isSuspended()) {
            abort(422, 'This contract is suspended.');
        }
    }

    private function assertNotTerminated(Contract $contract): void
    {
        if ($contract->terminated_at !== null) {
            abort(422, 'This contract has already been terminated.');
        }
    }

    private function assertSuspended(Contract $contract): void
    {
        if (! $contract->isSuspended()) {
            abort(422, 'This contract is not suspended.');
        }
    }

    private function assertQuoteClosedWon(Contract $contract): void
    {
        $contract->loadMissing('quote.quoteStatus');

        if ($contract->quote->quoteStatus?->group !== QuoteStatusGroup::ClosedWon) {
            abort(422, 'The linked quote is not currently closed_won.');
        }
    }

    private function assertClosedLost(int $statusId): void
    {
        // Eloquent's Builder::value() hydrates via first() and reads the
        // attribute through the model's cast pipeline, so this is already
        // the ContractStatusGroup enum, not a raw string — compare as such.
        $group = ContractStatus::query()->whereKey($statusId)->value('group');

        if ($group !== ContractStatusGroup::ClosedLost) {
            abort(422, 'The destination status must belong to the closed_lost group.');
        }
    }
}
