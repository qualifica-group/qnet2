<?php

namespace App\Enums;

/**
 * The mandatory system rows every persisted status configurator carries.
 * Pipeline statuses close on "Chiuso" (`Closed`). Opportunity statuses close
 * on "Chiuso con successo" (`Won`) plus the opportunity-only terminal row
 * "Persa" (`Lost`, ALWAYS last — App\Models\OpportunityStatus::SYSTEM_TAIL_KEYS).
 * Reward statuses (spec 0060) carry a single HEAD row instead, "In attesa"
 * (`Pending`, App\Models\RewardStatus::SYSTEM_HEAD_KEY — no tail). Persisted
 * as `pipeline_statuses.system_key`/`opportunity_statuses.system_key`/
 * `reward_statuses.system_key` (nullable — custom rows have none). Never
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
}
