<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\StatusGroup;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\User;
use App\Tables\RewardedReferents\RewardedReferentAdvancedFilterApplier;
use App\Tables\RewardedReferents\RewardedReferentAdvancedFilterCatalog;
use App\Tables\RewardedReferents\RewardedReferentColumnCatalog;
use App\Tables\RewardedReferents\RewardedReferentDerivedColumns;
use App\Tables\RewardedReferents\RewardedReferentRowMapper;
use App\Tables\RewardedReferents\RewardedReferentSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Table definition for the `rewarded-referents` domain (spec 0059): the
 * "Referenti con Buoni" aggregated, READ-ONLY view (D-6) over `referents`
 * rows that hold at least one `Reward` (AC-005). `modelClass()` is Referent
 * ONLY because that is what the row IS, not because ReferentPolicy governs
 * this domain — access runs through this module's OWN dedicated permission
 * set (`rewarded-referents.*`), mirroring RequestManagementTableDefinition's
 * exact precedent:
 *  - `authorizeViewAny()` is OVERRIDDEN: the AbstractTableDefinition
 *    fail-safe default would resolve `Gate::allows('viewAny',
 *    Referent::class)` -> ReferentPolicy -> `referents.viewAny`, the WRONG
 *    permission for this domain (AC-012: an actor with `referents.viewAny`
 *    but NOT `rewarded-referents.viewAny` must still get 403 here).
 *  - `authorizeUpdate()`/`authorizeDelete()` are deliberately NOT
 *    overridden. Unlike RequestManagement, this module declares NO editable
 *    column and NO delete row-action (D-6, scope/out) — so falling through
 *    to ReferentPolicy's real `referents.update`/`referents.delete` gates is
 *    the SAFE, correct default: it guarantees a `rewarded-referents.*` grant
 *    ALONE can never mutate or delete a Referent row through this view (the
 *    generic bulk-delete/inline-edit endpoints stay gated by the actual
 *    Referent permission, exactly like any other read of that model).
 *
 * `baseQuery()` scopes to referents with `whereHas('rewards')` (AC-005),
 * eager-loads `personalData.contacts`/`registries` (no N+1, AC-013), and
 * adds the three counters + last-assignment date as `withCount`/`withMax`
 * SELECT-list aliases — all ONE query regardless of row/reward count (D-2's
 * "active"/"completed" split additionally crosses the polymorphic
 * `Reward::source()` via `whereHasMorph`, so only Opportunity-origin rewards
 * ever increment them, per the acceptance criteria's accepted invariant
 * `rewards_count >= active + completed`).
 */
class RewardedReferentsTableDefinition extends AbstractTableDefinition
{
    public function __construct(
        private readonly RewardedReferentRowMapper $rowMapper,
        private readonly RewardedReferentSearch $search,
        private readonly RewardedReferentDerivedColumns $derivedColumns,
        private readonly RewardedReferentAdvancedFilterApplier $advancedFilterApplier,
    ) {}

    public function domain(): string
    {
        return 'rewarded-referents';
    }

    /**
     * @return class-string<Referent>
     */
    public function modelClass(): string
    {
        return Referent::class;
    }

    /**
     * Dedicated permission check (see class docblock): NEVER delegates to
     * ReferentPolicy.
     */
    public function authorizeViewAny(User $actor): bool
    {
        return $actor->can('rewarded-referents.viewAny');
    }

    /**
     * @return Builder<Referent>
     */
    public function baseQuery(): Builder
    {
        return Referent::query()
            ->whereHas('rewards')
            ->with(['personalData.contacts', 'registries'])
            ->withCount('rewards')
            ->withCount(['rewards as active_rewards_count' => function (Builder $rewards): void {
                $rewards->whereHasMorph(
                    'source',
                    [Opportunity::class],
                    static fn (Builder $source) => $source->whereHas(
                        'opportunityStatus',
                        static fn (Builder $status) => $status->whereIn('group', [
                            StatusGroup::Open->value,
                            StatusGroup::Pending->value,
                        ]),
                    ),
                );
            }])
            ->withCount(['rewards as completed_rewards_count' => function (Builder $rewards): void {
                $rewards->whereHasMorph(
                    'source',
                    [Opportunity::class],
                    static fn (Builder $source) => $source->whereHas(
                        'opportunityStatus',
                        static fn (Builder $status) => $status->where('group', StatusGroup::Closed->value),
                    ),
                );
            }])
            ->withMax(['rewards as last_assigned_at'], 'assigned_at');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return RewardedReferentColumnCatalog::columns();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return RewardedReferentColumnCatalog::filters();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return RewardedReferentColumnCatalog::actions();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return RewardedReferentAdvancedFilterCatalog::advancedFilters();
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [
            ['columnId' => 'last_assigned_at', 'direction' => 'desc'],
        ];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var Referent $row */
        return $this->rowMapper->map($row);
    }

    /**
     * No row-action exists for this READ-ONLY module (D-6, actions() is
     * empty) — the master/detail expand is an AG Grid affordance, not a
     * declared table action.
     *
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    /**
     * `name`'s extended search (referent_has_no_first_last_name); every
     * other searchable column would fall through to the generic engine
     * (none declared today).
     *
     * @param  Builder<Referent>  $query
     */
    public function applyDerivedSearch(Builder $query, string $columnId, string $pattern): bool
    {
        return $this->search->apply($query, $columnId, $pattern);
    }

    /**
     * `registries` (aggregated to-many), `rewards_count` and
     * `last_assigned_at` (both SELECT-list aliases MySQL cannot WHERE
     * against) are DERIVED — delegated to RewardedReferentDerivedColumns.
     * `active_rewards_count`/`completed_rewards_count` declare no filter
     * (`filterable: false`) so this hook is never reached for them; `name`
     * is a real, plain column handled entirely by the generic engine.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $columnConfig
     * @param  array<string, mixed>  $filter
     */
    public function applyDerivedFilter(Builder $query, string $columnId, array $columnConfig, array $filter): bool
    {
        return match ($columnId) {
            'registries' => $this->derivedColumns->applyRegistriesFilter($query, $filter),
            'rewards_count' => $this->derivedColumns->applyRewardsCountFilter($query, $filter),
            'last_assigned_at' => $this->derivedColumns->applyLastAssignedAtFilter($query, $filter),
            default => false,
        };
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the same 3 derived
     * columns; every other column falls through to the generic `SELECT
     * DISTINCT` on the real column.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $columnConfig
     * @return array<int, string>|null
     */
    public function distinctValues(User $actor, string $columnId, array $columnConfig, ?string $search, Builder $query, int $limit): ?array
    {
        return match ($columnId) {
            'registries' => $this->derivedColumns->distinctRegistries($query, $search, $limit),
            'rewards_count' => $this->derivedColumns->distinctRewardsCount($query, $search, $limit),
            'last_assigned_at' => $this->derivedColumns->distinctLastAssignedAt($query, $search, $limit),
            default => null,
        };
    }

    /**
     * All 6 advanced filters are domain-specific (see
     * RewardedReferentAdvancedFilterCatalog's docblock for why none fits the
     * generic default) — delegated entirely to
     * RewardedReferentAdvancedFilterApplier, no `parent::` fallback needed.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        return $this->advancedFilterApplier->apply($query, $name, $descriptor, $value);
    }
}
