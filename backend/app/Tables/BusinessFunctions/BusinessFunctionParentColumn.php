<?php

namespace App\Tables\BusinessFunctions;

use App\Models\BusinessFunction;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the `business-functions` domain's `parent` derived column (spec
 * 0010 REV): no real DB column of its own (the related parent's name),
 * mirroring ProductCategoriesTableDefinition's own `parent` derived column
 * for its self-referencing hierarchy. Extracted out of
 * BusinessFunctionsTableDefinition (file-size split, engineering.md §6).
 */
final class BusinessFunctionParentColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of names honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * Derived set filter via whereHas on the self-referencing `parent`
     * relation, matched by name. Bound parameters, capped cardinality.
     *
     * @param  Builder<BusinessFunction>  $query
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

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return true;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas('parent', static function (Builder $relatedQuery) use ($names): void {
                    $relatedQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): the root functions, with no parent.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('parent');
            }
        });

        return true;
    }

    /**
     * ORDER BY the parent's name via a correlated subquery, so sorting never
     * needs a row-multiplying JOIN on the main query.
     *
     * @param  Builder<BusinessFunction>  $query
     */
    public function applySort(Builder $query, string $direction): void
    {
        // Self-join: the subquery's own table is aliased (`parent_business_function`)
        // so it never collides with the outer query's `business_functions`.
        $subquery = BusinessFunction::query()
            ->from('business_functions as parent_business_function')
            ->select('parent_business_function.name')
            ->whereColumn('parent_business_function.id', 'business_functions.parent_id')
            ->limit(1);

        $query->orderBy($subquery, $direction);
    }

    /**
     * Excel-like distinct values (spec 0004/0005) for the derived `parent`
     * column: distinct parent NAMES among the functions matching `$query`
     * (already scoped by every OTHER active filter).
     *
     * @param  Builder<BusinessFunction>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $parentIds = (clone $query)->whereNotNull('parent_id')->select('parent_id');

        $values = DB::table('business_functions')
            ->whereIn('id', $parentIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$this->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave('parent')
            ->exists());
    }

    /**
     * Escape LIKE wildcards in user input so they are treated literally.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
