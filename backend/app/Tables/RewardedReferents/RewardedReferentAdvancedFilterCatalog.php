<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Enums\AdvancedFilterType;
use App\Models\QuoteWorkflowStatus;

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
 * `workflow_status` is a SET filter matched by the DISPLAYED status name
 * (spec 0082/0083, D-2/D-8): the origin Opportunity's status is COMPUTED
 * from its Quotes' own workflow statuses (falling back to the global default
 * workflow's `open` row when it has none) — App\Services\Opportunities\
 * OpportunityStatusScope is the single predicate every consumer of that
 * computed status shares, reused here instead of a second copy. The same
 * name is replicated across different Quote Workflows as distinct rows/ids,
 * hence name-, not id-, based. `opportunity_status` stays id-based (the
 * pipeline statuses table is a single flat set, no per-workflow
 * replication).
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
     * Distinct quote-workflow-status NAMES across every workflow (global set
     * + per-workflow overrides) — same reason the name replicates across
     * workflows as distinct ids.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private static function workflowStatusOptions(): array
    {
        return QuoteWorkflowStatus::query()
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (string $name): array => ['value' => $name, 'label' => $name])
            ->all();
    }
}
