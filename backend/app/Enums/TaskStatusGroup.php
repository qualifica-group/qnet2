<?php

namespace App\Enums;

/**
 * The PHASE every TaskStatus row carries (`task_statuses.group`, spec 0101
 * D-5 as rectified by the user directive 2026-09-04). A NEW enum dedicated
 * to this module, not a reuse of App\Enums\ContractStatusGroup or
 * App\Enums\StatusGroup: letting a status configurator fall back on another
 * module's enum would couple the two together, and the phase set is not the
 * same one (a Task validates twice — preanalysis and execution — so it needs
 * an `in_validation` phase a contract has no use for, and it closes
 * positive/negative rather than won/lost).
 *
 * WHY THIS EXISTS ALONGSIDE App\Enums\TaskStatusSystemKey, which used to BE
 * the phase set: `system_key` is UNIQUE, so it can mark at most ONE row per
 * value — it identifies the handful of PROTECTED rows the code must always
 * find. The phase is a MANY-to-one classification: five statuses can sit in
 * `open` at once. The two are orthogonal and both are needed, exactly as in
 * `contract_statuses` (spec 0072). Never mass-assignable on a system row
 * (App\Services\Statuses\SystemStatusGuard rejects it outright).
 */
enum TaskStatusGroup: string
{
    case Open = 'open';
    case Pending = 'pending';
    case InValidation = 'in_validation';
    case ClosedPositive = 'closed_positive';
    case ClosedNegative = 'closed_negative';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Whether reaching a status in this phase CLOSES the Task — the trigger
     * condition of App\Services\Tasks\TaskClosureFeedbackGuard (D-7).
     *
     * Deciding this on the PHASE rather than on `system_key` is what the
     * 2026-09-04 rectification bought: a custom, admin-created status placed
     * in a closing phase now closes the Task like any other, which is what
     * the D-5 "CONSEGUENZA DA APPROVARE" note anticipated as the fix.
     */
    public function isClosing(): bool
    {
        return match ($this) {
            self::ClosedPositive, self::ClosedNegative => true,
            default => false,
        };
    }
}
