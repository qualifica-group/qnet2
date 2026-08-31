<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

/**
 * Declarative column/filter/action catalogue for the `rewarded-referents`
 * domain (spec 0059): one row per Referent holding at least one Reward
 * (D-6, read-only aggregate view). Extracted out of
 * RewardedReferentsTableDefinition (file-size split, engineering.md §6):
 * pure data (no logic).
 *
 * `name` is a real `referents` column (sortable/filterable via the generic
 * engine) but its global quick-search is EXTENDED beyond it (referent has no
 * first/last name of its own — `referent_has_no_first_last_name`), delegated
 * to RewardedReferentSearch via `applyDerivedSearch()`. `registries` is an
 * AGGREGATED to-many column (via the `registries` BelongsToMany), filterable
 * but not sortable — no single related row to order by. `email`/`phone` are
 * COMPUTED display-only projections (RewardedReferentRowMapper), neither
 * sortable nor filterable (mirrors RequestColumnCatalog's client anagraphic
 * columns). `rewards_count`/`last_assigned_at` are `withCount`/`withMax`
 * ALIASES: sortable via the generic ORDER BY, but their FILTER is DERIVED
 * (MySQL cannot see a SELECT-list alias from WHERE) — delegated to
 * RewardedReferentDerivedColumns. `pending_rewards_count`/
 * `approved_rewards_count` are sortable-only aliases (no filter declared in
 * the contract): user directive 2026-08-31, they count by the buono's OWN
 * status group — `pending` and `closed_won` ("Approvato") — superseding the
 * origin-derived "attivi"/"completati" of spec 0059 D-2.
 *
 * No `actions()`: the module has no row-action beyond AG Grid's own
 * master/detail expand (D-4, a frontend affordance, not a declared table
 * action) — an `activity` action would require a `config/activity-log.php`
 * entry for this resource key, outside this task's write surface.
 */
final class RewardedReferentColumnCatalog
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function columns(): array
    {
        return [
            [
                'id' => 'id',
                'label' => 'table.columns.id',
                'type' => 'number',
                'visible' => false,
                'sortable' => true,
                'filterable' => false,
                'filterType' => null,
            ],
            [
                'id' => 'name',
                'label' => 'rewardedReferents.columns.name',
                'type' => 'text',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'text',
                // Global quick-search (spec 0009), EXTENDED to
                // personalData.first_name/last_name — see RewardedReferentSearch.
                'searchable' => true,
            ],
            [
                'id' => 'registries',
                'label' => 'rewardedReferents.columns.registries',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => true,
                'filterType' => 'set',
            ],
            [
                'id' => 'email',
                'label' => 'rewardedReferents.columns.email',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'phone',
                'label' => 'rewardedReferents.columns.phone',
                'type' => 'text',
                'visible' => true,
                'sortable' => false,
                'filterable' => false,
            ],
            [
                'id' => 'rewards_count',
                'label' => 'rewardedReferents.columns.rewardsCount',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'number',
            ],
            [
                'id' => 'pending_rewards_count',
                'label' => 'rewardedReferents.columns.pendingRewardsCount',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'approved_rewards_count',
                'label' => 'rewardedReferents.columns.approvedRewardsCount',
                'type' => 'number',
                'visible' => true,
                'sortable' => true,
                'filterable' => false,
            ],
            [
                'id' => 'last_assigned_at',
                'label' => 'rewardedReferents.columns.lastAssignedAt',
                'type' => 'date',
                'visible' => true,
                'sortable' => true,
                'filterable' => true,
                'filterType' => 'date',
            ],
        ];
    }

    /**
     * Only the filterable columns produce a filter descriptor, mirroring
     * RequestColumnCatalog's derivation.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function filters(): array
    {
        return array_values(array_map(
            static fn (array $column): array => [
                'columnId' => $column['id'],
                'type' => $column['filterType'],
            ],
            array_filter(
                self::columns(),
                static fn (array $column): bool => ($column['filterable'] ?? false) === true,
            ),
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function actions(): array
    {
        return [];
    }
}
