<?php

namespace App\Enums;

/**
 * The classification every RewardStatus row carries (`reward_statuses.group`).
 * A NEW enum dedicated to this module (spec 0073, D-5), not a reuse of
 * `QuoteStatusGroup`/`ContractStatusGroup`: letting a status configurator
 * fall back on another module's enum would couple the two together, the same
 * reasoning already applied when `ContractStatusGroup` was split off (spec
 * 0072, D-5). Same four values as those two — the terminal phase carries its
 * OUTCOME, ClosedWon (chiuso positivo) and ClosedLost (chiuso negativo, the
 * state the lifecycle automation moves a reward to when its originating
 * request is closed negatively). Never mass-assignable on a system row
 * (App\Services\Statuses\SystemStatusGuard rejects it outright).
 */
enum RewardStatusGroup: string
{
    case Open = 'open';
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
