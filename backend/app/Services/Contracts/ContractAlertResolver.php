<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatusGroup;
use App\Models\Contract;
use Illuminate\Support\Carbon;

/**
 * Contract lifecycle indicators (spec 0072, BR-6/D-4): calculated at read
 * time, never persisted. Shared by both the `ContractResource` (MT-02) and
 * `ContractsTableDefinition` (MT-04) so the two read paths can never disagree
 * on what counts as "in scadenza"/"da rinnovare".
 *
 * A contract whose status is classified `closed_lost`
 * (App\Enums\ContractStatusGroup) never produces an alert, whatever its
 * dates say — a disdetto/annullato contract is not "in scadenza" anymore.
 * Otherwise `expiring` wins over `renewal_due` when both windows would
 * apply (BR-6): the expiry is the more urgent of the two. A date that has
 * ALREADY passed does not raise its alert — the window is [today,
 * today + threshold], not open-ended into the past, since a contract that
 * quietly expired without any action is a stale-data problem, not something
 * this read-time indicator is meant to keep flagging forever.
 */
class ContractAlertResolver
{
    public function resolve(Contract $contract): ?string
    {
        if ($this->isClosedLost($contract)) {
            return null;
        }

        if ($this->isWithinWindow($this->daysToExpiry($contract), (int) config('contracts.expiring_within_days'))) {
            return 'expiring';
        }

        if ($this->isWithinWindow($this->daysToRenewal($contract), (int) config('contracts.renewal_within_days'))) {
            return 'renewal_due';
        }

        return null;
    }

    public function daysToExpiry(Contract $contract): ?int
    {
        return $this->daysFromToday($contract->expiry_date);
    }

    public function daysToRenewal(Contract $contract): ?int
    {
        return $this->daysFromToday($contract->renewal_date);
    }

    /**
     * Signed day count from today to $date: positive when $date is in the
     * future, negative when in the past, 0 when today, null when $date is
     * null.
     */
    private function daysFromToday(?Carbon $date): ?int
    {
        if ($date === null) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($date, false);
    }

    private function isWithinWindow(?int $days, int $windowDays): bool
    {
        return $days !== null && $days >= 0 && $days <= $windowDays;
    }

    private function isClosedLost(Contract $contract): bool
    {
        return $contract->contractStatus?->group === ContractStatusGroup::ClosedLost;
    }
}
