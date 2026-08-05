<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Reward;
use App\Services\Table\AdvancedFilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Server-side application of the 6 `rewarded-referents` advanced filters
 * (spec 0059, see RewardedReferentAdvancedFilterCatalog's docblock for why
 * none fits the generic default). Every filter is a `whereHas('rewards',
 * ...)` on the current Referent query — it narrows WHICH referents appear,
 * never what a row displays (`<advanced_filters>` note).
 *
 * `opportunity_status`/`workflow_status`/`operator` additionally cross the
 * POLYMORPHIC `Reward::source()` via `whereHasMorph('source',
 * [Opportunity::class], ...)`: this is what makes D-2's "the constraint
 * applies only to origins that possess a state" hold automatically — a
 * future non-Opportunity origin simply never matches these 3 (its rewards
 * still count toward `reward_type`/`assigned_at`, which stay on the `rewards`
 * row itself). `whereHasMorph` resolves the morph ALIAS through
 * `AppServiceProvider`'s strict `enforceMorphMap()`, never a raw FQCN.
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
     * `opportunity` — matches the reward's own polymorphic pointer directly
     * (`source_type`+`source_id`), never the related Opportunity's columns,
     * so a plain column match suffices (no `whereHasMorph` needed).
     *
     * @param  Builder<Referent>  $query
     */
    private function applyOpportunity(Builder $query, mixed $value): bool
    {
        if (! is_numeric($value)) {
            return true;
        }

        $opportunityId = (int) $value;
        $alias = Relation::getMorphAlias(Opportunity::class);

        $query->whereHas('rewards', static function (Builder $rewards) use ($alias, $opportunityId): void {
            $rewards->where('source_type', $alias)->where('source_id', $opportunityId);
        });

        return true;
    }

    /**
     * `workflow_status` — NAME-based set filter (catalogue docblock: the
     * same status name replicates across workflows as distinct ids),
     * crossing the polymorphic `source`.
     *
     * @param  Builder<Referent>  $query
     */
    private function applyWorkflowStatus(Builder $query, mixed $value): bool
    {
        $names = $this->stringValues($value);

        if ($names !== []) {
            $query->whereHas('rewards', static function (Builder $rewards) use ($names): void {
                $rewards->whereHasMorph(
                    'source',
                    [Opportunity::class],
                    static fn (Builder $source) => $source->whereHas(
                        'workflowStatus',
                        static fn (Builder $status) => $status->whereIn('name', $names),
                    ),
                );
            });
        }

        return true;
    }

    /**
     * `operator` — matches the source Opportunity's GA2 manager (pivot
     * `position` = Opportunity::OPERATOR_MANAGER_POSITION), crossing the
     * polymorphic `source`.
     *
     * @param  Builder<Referent>  $query
     */
    private function applyOperator(Builder $query, mixed $value): bool
    {
        $ids = $this->intIds($value);

        if ($ids !== []) {
            $query->whereHas('rewards', static function (Builder $rewards) use ($ids): void {
                $rewards->whereHasMorph(
                    'source',
                    [Opportunity::class],
                    static fn (Builder $source) => $source->whereHas(
                        'managers',
                        static fn (Builder $managers) => $managers
                            ->whereIn('users.id', $ids)
                            ->where('opportunity_user.position', Opportunity::OPERATOR_MANAGER_POSITION),
                    ),
                );
            });
        }

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
