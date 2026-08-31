<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\Referent;
use App\Models\Reward;
use App\Services\Opportunities\OpportunityStatusScope;
use App\Services\Rewards\RewardOriginScope;
use App\Services\Table\AdvancedFilterApplier;
use App\Support\ManagerPositions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Server-side application of the 6 `rewarded-referents` advanced filters
 * (spec 0059, see RewardedReferentAdvancedFilterCatalog's docblock for why
 * none fits the generic default). Every filter is a `whereHas('rewards',
 * ...)` on the current Referent query — it narrows WHICH referents appear,
 * never what a row displays (`<advanced_filters>` note).
 *
 * `opportunity`/`quote`/`workflow_status`/`operator` additionally cross the
 * POLYMORPHIC `Reward::source()` via RewardOriginScope: BOTH origins answer
 * them — an Opportunita' directly, an Offerta through its parent (user
 * directive 2026-08-31). Before that bridge existed these three closed on
 * `[Opportunity::class]` alone, so every Offerta-born buono silently dropped
 * out of them; the rewards themselves still count toward
 * `reward_type`/`assigned_at`, which stay on the `rewards` row itself.
 * `whereHasMorph` resolves the morph ALIAS through `AppServiceProvider`'s
 * strict `enforceMorphMap()`, never a raw FQCN.
 *
 * `MAX_FILTER_VALUES` caps the WHERE IN cardinality (defence in depth);
 * every value is bound, never interpolated (backend.md §8: `whereRaw`/
 * `orderByRaw` are SQLi sinks even inside Eloquent — none used here).
 */
final class RewardedReferentAdvancedFilterApplier
{
    private const int MAX_FILTER_VALUES = 200;

    public function __construct(private readonly AdvancedFilterApplier $filterApplier) {}

    /**
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function apply(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        return match ($name) {
            'reward_type' => $this->applyRewardType($query, $value),
            'reward_status' => $this->applyRewardStatus($query, $value),
            'opportunity' => $this->applyOpportunity($query, $value),
            'quote' => $this->applyQuote($query, $value),
            'workflow_status' => $this->applyWorkflowStatus($query, $value),
            'operator' => $this->applyOperator($query, $value),
            'assigned_at' => $this->applyAssignedAt($query, $descriptor, $value),
            default => false,
        };
    }

    /**
     * `reward_type` — a plain, direct-column set filter on the `rewards`
     * row itself (no morph crossing needed).
     *
     * @param  Builder<Referent>  $query
     */
    private function applyRewardType(Builder $query, mixed $value): bool
    {
        $ids = $this->intIds($value);

        if ($ids !== []) {
            $query->whereHas('rewards', static function (Builder $rewards) use ($ids): void {
                $rewards->whereIn('reward_type_id', $ids);
            });
        }

        return true;
    }

    /**
     * `reward_status` (spec 0060 §5) — a plain, direct-column set filter on
     * the `rewards` row itself, MIRRORED exactly from `reward_type` above (no
     * morph crossing needed: the persisted status lives on `rewards` itself,
     * spec 0060 D-5).
     *
     * @param  Builder<Referent>  $query
     */
    private function applyRewardStatus(Builder $query, mixed $value): bool
    {
        $ids = $this->intIds($value);

        if ($ids !== []) {
            $query->whereHas('rewards', static function (Builder $rewards) use ($ids): void {
                $rewards->whereIn('reward_status_id', $ids);
            });
        }

        return true;
    }

    /**
     * `opportunity` — the Opportunita' the buono ultimately comes from: the
     * origin itself when it IS one, its parent when the origin is an Offerta
     * (user directive 2026-08-31). No longer a plain `source_type`/`source_id`
     * column match: that answered only half of the origins, silently dropping
     * every Offerta-born buono of the very opportunity being filtered for.
     *
     * @param  Builder<Referent>  $query
     */
    private function applyOpportunity(Builder $query, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return true;
        }

        $opportunityId = (int) $value;

        $query->whereHas('rewards', static function (Builder $rewards) use ($opportunityId): void {
            RewardOriginScope::whereOpportunity(
                $rewards,
                static fn (Builder $source) => $source->whereKey($opportunityId),
            );
        });

        return true;
    }

    /**
     * `quote` — the mirror of applyOpportunity(): the OFFERTA the buono
     * ultimately belongs to, be it the origin itself or the single offer of
     * the origin Opportunita' (spec 0059 amendment A-01).
     *
     * @param  Builder<Referent>  $query
     */
    private function applyQuote(Builder $query, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return true;
        }

        $quoteId = (int) $value;

        $query->whereHas('rewards', static function (Builder $rewards) use ($quoteId): void {
            RewardOriginScope::whereQuote(
                $rewards,
                static fn (Builder $source) => $source->whereKey($quoteId),
            );
        });

        return true;
    }

    /**
     * `workflow_status` — NAME-based set filter (catalogue docblock: the
     * same status name replicates across workflows as distinct ids),
     * crossing the polymorphic `source`. Spec 0082/0083, D-2/D-8: the origin
     * Opportunity's status is COMPUTED off its Quotes (falling back to the
     * global default workflow's `open` row) — delegated to the shared
     * OpportunityStatusScope, the same predicate every other consumer of
     * that computed status uses, never a second copy against the removed
     * `Opportunity::workflowStatus()` relation.
     *
     * @param  Builder<Referent>  $query
     */
    private function applyWorkflowStatus(Builder $query, mixed $value): bool
    {
        $names = $this->stringValues($value);

        if ($names !== []) {
            $query->whereHas('rewards', static function (Builder $rewards) use ($names): void {
                RewardOriginScope::whereOpportunity(
                    $rewards,
                    static fn (Builder $source) => OpportunityStatusScope::whereNameIn($source, $names),
                );
            });
        }

        return true;
    }

    /**
     * `operator` — matches the source's OWN GA2 (spec 0087, D-10): the
     * Offerta's `operator_id` FK directly when the buono is Offerta-born, or
     * the Opportunity's own GA2 manager pivot slot (`position` =
     * `ManagerPositions::OPERATOR`) when it is Opportunity-born. Deliberately
     * NOT bridged through `RewardOriginScope::whereOpportunity()` like
     * `opportunity`/`workflow_status` above: that bridge would evaluate every
     * Offerta-born buono against its PARENT Opportunity's GA2 instead of its
     * own, silently ignoring the Offerta's own team the moment the two
     * diverge — exactly the bug this migration closes.
     *
     * @param  Builder<Referent>  $query
     */
    private function applyOperator(Builder $query, mixed $value): bool
    {
        $ids = $this->intIds($value);

        if ($ids === []) {
            return true;
        }

        $quoteAlias = Relation::getMorphAlias(Quote::class);

        $query->whereHas('rewards', static function (Builder $rewards) use ($ids, $quoteAlias): void {
            $rewards->whereHasMorph(
                'source',
                [Opportunity::class, Quote::class],
                static function (Builder $source) use ($ids, $quoteAlias): void {
                    if ($source->getModel()->getMorphClass() === $quoteAlias) {
                        $source->whereIn('operator_id', $ids);

                        return;
                    }

                    $source->whereHas(
                        'managers',
                        static fn (Builder $managers) => $managers
                            ->whereIn('users.id', $ids)
                            ->where('opportunity_user.position', ManagerPositions::OPERATOR),
                    );
                },
            );
        });

        return true;
    }

    /**
     * `assigned_at` — a real `rewards.assigned_at` column: delegated to the
     * shared AdvancedFilterApplier (already-validated date-range shape),
     * applied on the NESTED `rewards` relation query so `target` resolves as
     * a plain column of THAT table, not the outer Referent query.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $descriptor
     */
    private function applyAssignedAt(Builder $query, array $descriptor, mixed $value): bool
    {
        $query->whereHas('rewards', function (Builder $rewards) use ($descriptor, $value): void {
            /** @var Builder<Reward> $rewards */
            $this->filterApplier->apply($rewards, $descriptor['type'], 'assigned_at', $value, $descriptor);
        });

        return true;
    }

    /**
     * @return array<int, int>
     */
    private function intIds(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_slice(array_values(array_filter(
            array_map(static fn (mixed $item): ?int => is_numeric($item) ? (int) $item : null, $values),
            static fn (?int $item): bool => $item !== null,
        )), 0, self::MAX_FILTER_VALUES);
    }

    /**
     * @return array<int, string>
     */
    private function stringValues(mixed $value): array
    {
        $values = is_array($value) ? $value : [$value];

        return array_slice(array_values(array_filter(
            $values,
            static fn (mixed $item): bool => is_string($item) && $item !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }
}
