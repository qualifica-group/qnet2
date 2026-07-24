<?php

declare(strict_types=1);

namespace App\Tables\RewardedReferents;

use App\Models\Referent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Filter + Excel-like distinct-values resolution for the `rewarded-referents`
 * domain's THREE columns that have no plain real DB column to WHERE/DISTINCT
 * against (spec 0059): `registries` (an AGGREGATED to-many via the
 * `registries` BelongsToMany), `rewards_count` and `last_assigned_at` (both
 * `withCount`/`withMax` SELECT-list ALIASES — MySQL cannot see either from
 * WHERE, only ORDER BY, which is why sort stays generic).
 *
 * `rewards_count` is a plain COUNT, so its filter is expressed portably via
 * `has('rewards', operator, n)` (a bound correlated-subquery WHERE),
 * mirroring RoleUsersCountColumn/ProductCategoryCountColumn. `last_assigned_at`
 * is a MAX, which `has()` cannot express for a `<=`/range upper bound (an
 * "any reward before X" existence check is NOT equivalent to "the LATEST
 * reward is before X"): its filter instead wraps the already-filtered query
 * as a derived table (`fromSub`, same RoleUsersCountColumn precedent used
 * there only for distinct-values) and narrows the outer query by the
 * matching primary keys — correct for every comparison direction and still a
 * portable, bound `WHERE id IN (...)`.
 */
final class RewardedReferentDerivedColumns
{
    /**
     * Maximum number of names honoured in the `registries` set filter. Caps
     * the WHERE IN cardinality (defence in depth); excess values ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * `registries` set filter: whereHas by the related registry's own name,
     * bound and never raw.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyRegistriesFilter(Builder $query, array $filter): bool
    {
        $names = $this->stringValues($filter['values'] ?? null);

        if ($names !== []) {
            $query->whereHas('registries', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * `rewards_count` NUMBER condition filter (equals/notEqual/greaterThan
     * (OrEqual)/lessThan(OrEqual)/inRange), applied as a count comparison on
     * the `rewards` relation via has().
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyRewardsCountFilter(Builder $query, array $filter): bool
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';

        if ($type === 'inRange') {
            $from = $this->intOrNull($filter['filter'] ?? null);
            $to = $this->intOrNull($filter['filterTo'] ?? null);

            if ($from !== null) {
                $query->has('rewards', '>=', $from);
            }

            if ($to !== null) {
                $query->has('rewards', '<=', $to);
            }

            return true;
        }

        $value = $this->intOrNull($filter['filter'] ?? null);

        if ($value === null) {
            return true; // blank / notBlank / malformed -> no constraint
        }

        $query->has('rewards', $this->numberOperator($type), $value);

        return true;
    }

    /**
     * `last_assigned_at` DATE condition filter (equals/notEqual/greaterThan/
     * lessThan/inRange), applied against the true MAX via the wrapped-table
     * precedent (see class docblock) — single condition only (no AND/OR
     * combinable, mirroring the aggregate columns' plainer `number`/`date`
     * condition widget elsewhere in this codebase).
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyLastAssignedAtFilter(Builder $query, array $filter): bool
    {
        $type = is_string($filter['type'] ?? null) ? $filter['type'] : 'equals';
        $from = is_string($filter['dateFrom'] ?? null) ? $filter['dateFrom'] : null;
        $to = is_string($filter['dateTo'] ?? null) ? $filter['dateTo'] : null;

        $wrapped = DB::query()->fromSub((clone $query), 'rewarded_referents_dates')->select('id');

        if ($type === 'inRange' && $from !== null && $to !== null) {
            $wrapped->whereBetween('last_assigned_at', [$from, $to]);
        } elseif ($from !== null) {
            match ($type) {
                'greaterThan' => $wrapped->where('last_assigned_at', '>', $from),
                'lessThan' => $wrapped->where('last_assigned_at', '<', $from),
                'notEqual' => $wrapped->whereDate('last_assigned_at', '!=', $from),
                default => $wrapped->whereDate('last_assigned_at', '=', $from),
            };
        } else {
            return true; // blank / malformed -> no constraint
        }

        $query->whereIn('referents.id', $wrapped->pluck('id'));

        return true;
    }

    /**
     * Excel-like distinct registry names (spec 0004/0005), scoped by
     * `$query` (already narrowed by every OTHER active filter) via the
     * `referent_registry` pivot.
     *
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    public function distinctRegistries(Builder $query, ?string $search, int $limit): array
    {
        $referentIds = (clone $query)->select('referents.id');

        return DB::table('referent_registry')
            ->join('registries', 'registries.id', '=', 'referent_registry.registry_id')
            ->whereIn('referent_registry.referent_id', $referentIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('registries.name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('registries.name')
            ->limit($limit)
            ->pluck('registries.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Excel-like distinct `rewards_count` values, via the same wrapped-table
     * precedent as RoleUsersCountColumn.
     *
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    public function distinctRewardsCount(Builder $query, ?string $search, int $limit): array
    {
        $counts = DB::query()->fromSub((clone $query), 'rewarded_referents_counts')->select('rewards_count')->distinct();

        if ($search !== null && $search !== '') {
            $counts->where('rewards_count', 'like', '%'.$this->escapeLike($search).'%');
        }

        return $counts
            ->orderBy('rewards_count')
            ->limit($limit)
            ->pluck('rewards_count')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * Excel-like distinct `last_assigned_at` values (date strings).
     *
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    public function distinctLastAssignedAt(Builder $query, ?string $search, int $limit): array
    {
        $dates = DB::query()->fromSub((clone $query), 'rewarded_referents_last_assigned')
            ->select('last_assigned_at')
            ->whereNotNull('last_assigned_at')
            ->distinct();

        if ($search !== null && $search !== '') {
            $dates->where('last_assigned_at', 'like', '%'.$this->escapeLike($search).'%');
        }

        return $dates
            ->orderBy('last_assigned_at')
            ->limit($limit)
            ->pluck('last_assigned_at')
            ->map(static fn (mixed $value): string => (string) $value)
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function stringValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);
    }

    private function intOrNull(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    private function numberOperator(string $type): string
    {
        return match ($type) {
            'notEqual' => '!=',
            'lessThan' => '<',
            'lessThanOrEqual' => '<=',
            'greaterThan' => '>',
            'greaterThanOrEqual' => '>=',
            default => '=', // 'equals'
        };
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
