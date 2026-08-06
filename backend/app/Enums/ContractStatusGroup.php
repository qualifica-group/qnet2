<?php

namespace App\Enums;

/**
 * The classification every ContractStatus row carries
 * (`contract_statuses.group`). A NEW enum dedicated to this module (spec
 * 0072, D-5), not a reuse of `App\Enums\WorkflowStatusGroup`: allowing a
 * status configurator to fall back on another module's enum would couple the
 * two modules together. Same four base values as `WorkflowStatusGroup`
 * minus its `validated` phase (open | pending | closed_won | closed_lost —
 * a contract only ever exists once a quote reaches `closed_won`, so
 * "closed_won" here classifies a contract status the same automation may
 * route back to via reactivation). Never mass-assignable on a system row
 * (App\Services\Statuses\SystemStatusGuard rejects it outright).
 */
enum ContractStatusGroup: string
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
