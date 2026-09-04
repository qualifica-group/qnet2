<?php

namespace App\Services\Statuses;

use App\Enums\StatusSystemKey;
use App\Models\ContractStatus;
use App\Models\PipelineStatus;
use App\Models\RewardStatus;
use App\Models\TaskStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The system-status protection rules (spec 0039, D-2; extended to
 * reward_statuses by spec 0060; extended to contract_statuses by spec
 * 0072; extended to task_statuses by spec 0101), shared verbatim by every
 * status configurator (pipeline_statuses, reward_statuses,
 * contract_statuses, task_statuses): every mandatory row cannot be deleted,
 * and only the MUTABLE_SYSTEM_FIELDS below may ever change — every OTHER
 * submitted attribute is rejected (pipeline/contract: `group`,
 * App\Enums\StatusGroup or module-specific equivalent, fixed at migration
 * time; reward: `description`/`is_active`/`sort_order`, spec 0060 BR-3).
 * Mirrors the precedent guard for a single protected system row,
 * RoleService::guardSystemRoleMutation (the `super-admin` role). The
 * `opportunity_statuses`/`quote_statuses` configurators this class once also
 * covered are gone (spec 0082/0083).
 */
class SystemStatusGuard
{
    /**
     * The only attributes a system row may ever have changed. `icon` and
     * `completion_percentage` were added for task_statuses (spec 0101):
     * both are safe for the three older configurators, whose rules() do not
     * declare either key, so neither can ever reach this guard from their
     * forms (AC-048).
     *
     * @var array<int, string>
     */
    private const array MUTABLE_SYSTEM_FIELDS = ['name', 'color', 'icon', 'completion_percentage'];

    /**
     * @throws HttpException 422
     */
    public function assertDeletable(PipelineStatus|RewardStatus|ContractStatus|TaskStatus $status): void
    {
        if (! $status->isSystem()) {
            return;
        }

        abort(422, "The '{$status->name}' status is a system status and cannot be deleted.");
    }

    /**
     * @param  array<string, mixed>  $submittedAttributes  the attributes the
     *                                                     client actually submitted (UpdatePipelineStatusData/
     *                                                     UpdateRewardStatusData::submittedAttributes())
     *                                                     — checked by KEY, so an update that touches ONLY name/color is
     *                                                     always allowed on a system row, whatever other fields the
     *                                                     concrete resource happens to carry.
     *
     * @throws HttpException 422
     */
    public function assertUpdatable(PipelineStatus|RewardStatus|ContractStatus|TaskStatus $status, array $submittedAttributes): void
    {
        if (! $status->isSystem()) {
            return;
        }

        $restrictedKeys = array_diff(array_keys($submittedAttributes), self::MUTABLE_SYSTEM_FIELDS);

        if ($restrictedKeys !== []) {
            // Wording deliberately left as-is while widening MUTABLE_SYSTEM_FIELDS:
            // nine existing tests across pipeline/contract/reward assert this exact
            // string, and AC-048 requires that suite to stay green. The restricted
            // KEY set, not this sentence, is what the guard enforces.
            abort(422, 'System statuses accept only name and color changes.');
        }
    }

    /**
     * The id of the mandatory "Nuovo" system row for $modelClass — the
     * fallback assigned when a Project/(standalone) Campaign is created
     * without an explicit status FK (spec 0039, D-3). Resolved by
     * `system_key`, never by name (D-3: "query per system_key, non per
     * nome").
     *
     * @param  class-string<PipelineStatus>|class-string<ContractStatus>  $modelClass
     *
     * @throws HttpException 500 if the
     *                       mandatory row is somehow missing (should never happen post-migration,
     *                       defense in depth)
     */
    public function resolveNewStatusId(string $modelClass): int
    {
        $id = $modelClass::query()->where('system_key', StatusSystemKey::New->value)->value('id');

        if ($id === null) {
            abort(500, "The system 'new' status is missing for {$modelClass}.");
        }

        return (int) $id;
    }
}
