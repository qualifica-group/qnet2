<?php

namespace App\Enums;

/**
 * The mandatory system rows every persisted status configurator carries.
 * Pipeline statuses close on "Chiuso" (`Closed`). Opportunity statuses close
 * on "Chiuso con successo" (`Won`) plus the opportunity-only terminal row
 * "Persa" (`Lost`, ALWAYS last — App\Models\OpportunityStatus::SYSTEM_TAIL_KEYS).
 * Reward statuses (spec 0060, extended by spec 0073 D-6) carry ONE head row,
 * "In attesa" (`Pending`, App\Models\RewardStatus::SYSTEM_HEAD_KEYS), plus a
 * `Won`/`Lost` tail named after the decision a buono gets — "Approvato" and
 * "Negato" (user directive 2026-08-03). Contract
 * statuses (spec 0072, D-2) carry a HEAD row, "Da validare" (`New`), plus a
 * three-row TAIL — "Sospeso" (`Suspended`), "Annullato" (`Cancelled`),
 * "Disdetto" (`Terminated`) — App\Models\ContractStatus::SYSTEM_TAIL_KEYS, in
 * that declared order. Persisted as `pipeline_statuses.system_key`/
 * `opportunity_statuses.system_key`/`reward_statuses.system_key`/
 * `contract_statuses.system_key` (nullable — custom rows have none). Never
 * mass-assignable (App\Services\Statuses\SystemStatusGuard/StatusOrderManager
 * are the only writers).
 */
enum StatusSystemKey: string
{
    case New = 'new';
    case Closed = 'closed';
    case Won = 'won';
    case Lost = 'lost';
    case Pending = 'pending';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Terminated = 'terminated';
}
