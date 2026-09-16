<?php

namespace App\Tables\LeadImports;

use App\Models\ImportRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `import-runs` domain's `user` derived column (user decision
 * 2026-09-16): the name of the operator who started the run, via
 * `import_runs.user_id`. Mirrors ReferentUserColumn (filter/sort/distinct)
 * minus the blank entry: `import_runs.user_id` is NOT NULL, so a run always
 * has an operator.
 */
final class ImportRunUserColumn
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
     * @param  Builder<ImportRun>  $query
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

        if ($names === []) {
            return true;
        }

        $query->whereHas('user', static function (Builder $relatedQuery) use ($names): void {
            $relatedQuery->whereIn('name', $names);
        });

        return true;
    }

    /**
     * ORDER BY the operator's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<ImportRun>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        $subquery = User::query()
            ->select('name')
            ->whereColumn('users.id', 'import_runs.user_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): distinct operator NAMES
     * among the runs matching `$query` (already scoped by every OTHER active
     * filter).
     *
     * @param  Builder<ImportRun>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('user_id');

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
