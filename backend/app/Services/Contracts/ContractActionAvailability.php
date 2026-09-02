<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Enums\ContractStatusGroup;
use App\Models\Contract;

/**
 * Which domain actions a contract admits, driven by the GROUP of its CURRENT
 * status (user directive 2026-08-31 rev.2, which supersedes the earlier
 * `validated_at`/`terminated_at` reading):
 *
 * - open | pending → "Modifica dati", "Modifica stato", "Valida", "Disdici"
 * - closed_won     → "Disdici", "Programma" (spec 0095: generates work
 *   orders from the offer's lines, no longer sets dates) and "Riapri
 *   contratto", nothing else
 * - closed_lost    → "Riapri contratto", nothing else
 *
 * Single source of truth for both surfaces that offer those actions —
 * App\Authorization\ContractsAuthorization (the detail's
 * `permissions.actions`) and App\Tables\ContractsTableDefinition (the grid's
 * row actions) — so the two can never drift. It is an availability rule, NOT
 * an authorization one: each caller still ANDs it with the actor's ability,
 * and ContractActionService re-asserts the same rules server-side (422).
 *
 * SUSPENSION is an orthogonal axis the directive does not mention, and the
 * pre-existing flow (BR-2/D-3) must keep working: a suspended contract sits
 * on the `pending` system row "Sospeso", so it KEEPS "Riapri contratto"
 * and loses "Valida" — the validate endpoint refuses a suspended contract
 * outright, so offering it would be a dead affordance.
 */
class ContractActionAvailability
{
    public function mayValidate(Contract $contract): bool
    {
        return $this->isWorking($contract) && ! $contract->isSuspended();
    }

    /**
     * "Programma" (spec 0095, D-2): identical rule to the RETIRED
     * `maySchedule()` it replaces — same status group, same doubled gate
     * (each caller ANDs this with the actor's `contracts.program` ability).
     */
    public function mayProgram(Contract $contract): bool
    {
        return $this->group($contract) === ContractStatusGroup::ClosedWon;
    }

    public function mayTerminate(Contract $contract): bool
    {
        return $this->isWorking($contract) || $this->group($contract) === ContractStatusGroup::ClosedWon;
    }

    /** "Modifica dati": the PATCH-editable fields, only while the contract is still working. */
    public function mayEdit(Contract $contract): bool
    {
        return $this->isWorking($contract);
    }

    /** "Modifica stato": moves the contract WITHIN the open/pending groups, never across a closure. */
    public function mayChangeStatus(Contract $contract): bool
    {
        return $this->isWorking($contract);
    }

    /**
     * "Riapri contratto" is the only way out of a closure — negative
     * (disdetta/annullamento) or positive (user directive 2026-08-31 rev.3:
     * a validated contract must be reopenable too) — and the pre-existing
     * way out of a suspension (BR-2/D-3).
     */
    public function mayReactivate(Contract $contract): bool
    {
        return $this->isClosed($contract) || $contract->isSuspended();
    }

    /** Either closure has happened: the contract is no longer in the working phase. */
    private function isClosed(Contract $contract): bool
    {
        return in_array($this->group($contract), [ContractStatusGroup::ClosedWon, ContractStatusGroup::ClosedLost], true);
    }

    /** Still in the working phase: neither closure has happened yet. */
    private function isWorking(Contract $contract): bool
    {
        return in_array($this->group($contract), [ContractStatusGroup::Open, ContractStatusGroup::Pending], true);
    }

    private function group(Contract $contract): ?ContractStatusGroup
    {
        $contract->loadMissing('contractStatus');

        return $contract->contractStatus?->group;
    }
}
