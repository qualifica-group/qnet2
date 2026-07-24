<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Enums\AdvancedFilterType;
use App\Models\OpportunityStatus;
use App\Models\OpportunityWorkflowStatus;

/**
 * Advanced-filter catalogue for the `rewarded-referents` domain (spec 0059,
 * `<advanced_filters>`). Every entry is applied as a `whereHas('rewards',
 * ...)` — they narrow WHICH referents appear, never what a row displays —
 * delegated entirely to RewardedReferentAdvancedFilterApplier: none of the 6
 * fits the generic default (`AdvancedFilterApplier::applyRelation()` only
 * matches a DIRECT relation's own primary key, but `reward_type`/`operator`/
 * `assigned_at` target a column/pivot ONE HOP INSIDE the `rewards` relation,
 * and `opportunity`/`opportunity_status`/`workflow_status` must additionally
 * cross the POLYMORPHIC `source` — D-2's "the constraint applies only to
 * origins that possess a state" — none of that a plain relation-by-id can
 * express).
 *
 * `workflow_status` is a SET filter matched by the related row's NAME, not
 * id (mirroring RequestAdvancedFilterCatalog's own precedent): the same
 * status name is replicated across different Opportunity Workflows as
 * distinct rows/ids. `opportunity_status` stays id-based (the pipeline
 * statuses table is a single flat set, no per-workflow replication).
 *
 * `opportunity`'s `source: {resource: 'opportunities'}` has NO backing
 * `opportunities/for-select` route today (spec 0040 left it out of scope) —
 * the server-side filter logic below is correct and independent of that
 * gap, but the frontend widget has no live autocomplete source until a
 * future task adds it (flagged to the team, out of this module's write
 * surface).
 *
 * `reward_status` (spec 0060 §5) is a plain, direct-column set filter on the
 * `rewards` row itself — same shape as `reward_type`, MIRRORED exactly (id
 * source `reward-statuses/for-select`, target `reward_status_id`).
 */
final class RewardedReferentAdvancedFilterCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function advancedFilters(): array
    {
        return [
            [
                'name' => 'reward_type',
                'label' => 'rewardedReferents.advancedFilters.rewardType',
                'type' => AdvancedFilterType::Relation,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'reward-types'],
                'target' => 'reward_type_id',
            ],
            [
                'name' => 'opportunity',
                'label' => 'rewardedReferents.advancedFilters.opportunity',
                'type' => AdvancedFilterType::AsyncSearch,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'source' => ['resource' => 'opportunities'],
                'target' => 'source_id',
            ],
            [
                'name' => 'opportunity_status',
                'label' => 'rewardedReferents.advancedFilters.opportunityStatus',
                'type' => AdvancedFilterType::Multiselect,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'options' => self::opportunityStatusOptions(),
                'target' => 'opportunity_status_id',
            ],
            [
                'name' => 'workflow_status',
                'label' => 'rewardedReferents.advancedFilters.workflowStatus',
                'type' => AdvancedFilterType::Multiselect,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'options' => self::workflowStatusOptions(),
                'target' => 'workflow_status',
            ],
            [
                'name' => 'operator',
                'label' => 'rewardedReferents.advancedFilters.operator',
                'type' => AdvancedFilterType::Relation,
                'order' => 5,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'operator_id',
            ],
            [
                'name' => 'assigned_at',
                'label' => 'rewardedReferents.advancedFilters.assignedAt',
                'type' => AdvancedFilterType::DateRange,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'assigned_at',
            ],
            [
                'name' => 'reward_status',
                'label' => 'rewardedReferents.advancedFilters.rewardStatus',
                'type' => AdvancedFilterType::Relation,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'reward-statuses'],
                'target' => 'reward_status_id',
            ],
        ];
    }

    /**
     * The full opportunity-statuses set, id-based (a single flat pipeline,
     * no per-workflow replication).
     *
     * @return array<int, array{value: int, label: string}>
     */
    private static function opportunityStatusOptions(): array
    {
        return OpportunityStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name'])
            ->map(static fn (OpportunityStatus $status): array => ['value' => $status->id, 'label' => $status->name])
            ->all();
    }

    /**
     * Distinct workflow-status NAMES across every workflow (global set +
     * per-workflow overrides), mirroring RequestAdvancedFilterCatalog::
     * workflowStatusOptions() exactly (same reason: the same name replicates
     * across workflows as distinct ids).
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function workflowStatusOptions(): array
    {
        return OpportunityWorkflowStatus::query()
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (string $name): array => ['value' => $name, 'label' => $name])
            ->all();
    }
}
