<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\ContractStatusGroup;
use App\Models\Contract;

/**
 * Which domain actions a contract's CURRENT lifecycle admits (user directive
 * 2026-08-31), independently of what the actor is allowed to do:
 *
 * - not validated yet  → "Valida" and "Disdici"
 * - validated          → "Programma" and "Disdici" (never "Valida" again)
 * - disdetto           → nothing at all
 *
 * Single source of truth for both surfaces that offer those actions —
 * App\Authorization\ContractsAuthorization (the detail's
 * `permissions.actions`) and App\Tables\ContractsTableDefinition (the grid's
 * row actions) — so the two can never drift. It is an availability rule, NOT
 * an authorization one: each caller still ANDs it with the actor's ability,
 * and ContractActionService re-asserts the same lifecycle server-side (422).
 *
 * "Validated" reads BOTH the `validated_at` stamp and the status group:
 * validating lands the contract on the `closed_won` "Validato" row, so the
 * two normally agree — the OR also covers a contract moved onto a
 * positive-outcome status by other means.
 */
class ContractActionAvailability
{
    public function mayValidate(Contract $contract): bool
    {
        // A suspended contract has no working status to validate into
        // (ContractActionService::validate() 422s on it).
        return ! $this->isTerminated($contract)
            && ! $this->isValidated($contract)
            && ! $contract->isSuspended();
    }

    public function maySchedule(Contract $contract): bool
    {
        return ! $this->isTerminated($contract) && $this->isValidated($contract);
    }

    public function mayTerminate(Contract $contract): bool
    {
        return ! $this->isTerminated($contract);
    }

    /**
     * "Riattiva contratto" serves both the suspended contract (BR-2/D-3,
     * restores the pre-suspension status) and — since the user directive of
     * 2026-08-31 — the disdetto one, which is otherwise a dead end.
     */
    public function mayReactivate(Contract $contract): bool
    {
        return $contract->isSuspended() || $this->isTerminated($contract);
    }

    /**
     * "Modifica dati" (PATCH): available through the whole lifecycle, gone
     * once the contract is disdetto — the only way out of that state is
     * "Riattiva contratto".
     */
    public function mayEdit(Contract $contract): bool
    {
        return ! $this->isTerminated($contract);
    }

    private function isTerminated(Contract $contract): bool
    {
        return $contract->terminated_at !== null;
    }

    private function isValidated(Contract $contract): bool
    {
        $contract->loadMissing('contractStatus');

        return $contract->validated_at !== null
            || $contract->contractStatus?->group === ContractStatusGroup::ClosedWon;
    }
}
