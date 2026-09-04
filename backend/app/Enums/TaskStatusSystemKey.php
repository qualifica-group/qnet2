<?php

namespace App\Enums;

/**
 * The six mandatory phases every Task status configurator carries (spec
 * 0101, D-5), persisted as `task_statuses.system_key` (nullable — custom
 * rows have none). Unlike `App\Enums\StatusSystemKey` (contract/pipeline/
 * reward statuses), these six keys ARE the fases: there is no separate
 * `group` column, so this enum is the ONLY place the phase set is declared.
 * Never mass-assignable (the six rows are created by the migration; only
 * `App\Services\Statuses\SystemStatusGuard` protects them afterwards).
 */
enum TaskStatusSystemKey: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case InValidation = 'in_validation';
    case ClosedPositive = 'closed_positive';
    case ClosedNegative = 'closed_negative';

    /**
     * Whether this phase is a closing one — the trigger condition for
     * `App\Services\Tasks\TaskClosureFeedbackGuard` (D-7).
     */
    public function isClosing(): bool
    {
        return match ($this) {
            self::ClosedPositive, self::ClosedNegative => true,
            default => false,
        };
    }
}
