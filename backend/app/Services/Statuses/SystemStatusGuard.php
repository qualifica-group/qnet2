<?php

namespace App\Services\Statuses;

use App\Enums\StatusSystemKey;
use App\Models\ContractStatus;
use App\Models\OpportunityStatus;
use App\Models\PipelineStatus;
use App\Models\QuoteStatus;
use App\Models\RewardStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The system-status protection rules (spec 0039, D-2; extended to
 * opportunity_statuses by spec 0043; extended to reward_statuses by spec
 * 0060; extended to quote_statuses by spec 0065; extended to
 * contract_statuses by spec 0072), shared verbatim by every status
 * configurator (pipeline_statuses, opportunity_statuses, reward_statuses,
 * quote_statuses, contract_statuses): every mandatory row cannot be deleted,
 * and only its `name`/`color` may ever change — every OTHER submitted
 * attribute is rejected (pipeline/opportunity/quote/contract: `group`,
 * App\Enums\StatusGroup or module-specific equivalent, fixed at migration
 * time; reward: `description`/`is_active`/`sort_order`, spec 0060 BR-3).
 * Mirrors the precedent guard for a single protected system row,
 * RoleService::guardSystemRoleMutation (the `super-admin` role).
 */
class SystemStatusGuard
{
    /**
     * The only two attributes a system row may ever have changed.
     *
     * @var array<int, string>
     */
    private const array MUTABLE_SYSTEM_FIELDS = ['name', 'color'];

    /**
     * @throws HttpException 422
     */
    public function assertDeletable(PipelineStatus|OpportunityStatus|RewardStatus|QuoteStatus|ContractStatus $status): void
    {
        if (! $status->isSystem()) {
            return;
        }

        abort(422, "The '{$status->name}' status is a system status and cannot be deleted.");
    }

    /**
     * @param  array<string, mixed>  $submittedAttributes  the attributes the
     *                                                     client actually submitted (UpdatePipelineStatusData/
     *                                                     UpdateOpportunityStatusData/UpdateRewardStatusData::submittedAttributes())
     *                                                     — checked by KEY, so an update that touches ONLY name/color is
     *                                                     always allowed on a system row, whatever other fields the
     *                                                     concrete resource happens to carry.
     *
     * @throws HttpException 422
     */
    public function assertUpdatable(PipelineStatus|OpportunityStatus|RewardStatus|QuoteStatus|ContractStatus $status, array $submittedAttributes): void
    {
        if (! $status->isSystem()) {
            return;
        }

        $restrictedKeys = array_diff(array_keys($submittedAttributes), self::MUTABLE_SYSTEM_FIELDS);

        if ($restrictedKeys !== []) {
            abort(422, 'System statuses accept only name and color changes.');
        }
    }

    /**
     * The id of the mandatory "Nuovo" system row for $modelClass — the
     * fallback assigned when a Project/(standalone) Campaign/Opportunity is
     * created without an explicit status FK (spec 0039, D-3; spec 0043).
     * Resolved by `system_key`, never by name (D-3: "query per system_key,
     * non per nome").
     *
     * @param  class-string<PipelineStatus>|class-string<OpportunityStatus>|class-string<QuoteStatus>|class-string<ContractStatus>  $modelClass
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
