<?php

namespace App\Tables\Referents;

use App\Models\Referent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `referents` domain's `user` derived column (spec 0090, D-3):
 * no real DB column of its own (the linked user's name via `referents.user_id`),
 * mirroring BusinessFunctionsTableDefinition's own `manager` derived column
 * exactly (filter/sort/distinct). Extracted out of ReferentsTableDefinition
 * (file-size split, engineering.md §6) rather than accodato — that file was
 * already past the 300-line soft limit before this addition (spec 0090, R-4).
 */
final class ReferentUserColumn
{
    /**
     * Maximum number of names honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Derived set filter via whereHas on the `user` relation, matched by
     * name. Bound parameters, capped cardinality.
     *
     * @param  Builder<Referent>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): bool
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return true;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names !== []) {
            $query->whereHas('user', static function (Builder $relatedQuery) use ($names): void {
                $relatedQuery->whereIn('name', $names);
            });
        }

        return true;
    }

    /**
     * ORDER BY the linked user's name via a correlated subquery, so sorting
     * never needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<Referent>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $subquery = User::query()
            ->select('name')
            ->whereColumn('users.id', 'referents.user_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `user`
     * column: distinct linked-user NAMES among the referents matching
     * `$query` (already scoped by every OTHER active filter).
     *
     * @param  Builder<Referent>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->whereNotNull('user_id')->select('user_id');

        return DB::table('users')
            ->whereIn('id', $userIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
