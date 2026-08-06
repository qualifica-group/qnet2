<?php

namespace App\Enums;

/**
 * The classification every RewardStatus row carries (`reward_statuses.group`).
 * A NEW enum dedicated to this module (spec 0073, D-5), not a reuse of
 * `App\Enums\WorkflowStatusGroup`/`ContractStatusGroup`: letting a status
 * configurator fall back on another module's enum would couple the two
 * together, the same reasoning already applied when `ContractStatusGroup`
 * was split off (spec 0072, D-5).
 *
 * THREE values, not the four of those two enums (user directive 2026-08-03,
 * amendment to spec 0073): a buono is either waiting for a decision (Pending,
 * the phase it is born on) or decided — ClosedWon ("Approvato") / ClosedLost
 * ("Negato"). The `open` phase was dropped because it had no meaning here,
 * which is also why Pending is the DEFAULT of a newly created custom row (the
 * migration moved every pre-existing `open` row onto it). Never
 * mass-assignable on a system row (App\Services\Statuses\SystemStatusGuard
 * rejects it outright).
 */
enum RewardStatusGroup: string
{
    case Pending = 'pending';
    case ClosedWon = 'closed_won';
    case ClosedLost = 'closed_lost';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
