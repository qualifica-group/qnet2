<?php

namespace App\Tables\Users;

use App\Models\EmploymentProfile;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `business_function` grid column on `users` (spec 0015, revised by spec
 * 0111 D-1): a profile no longer carries a single `business_function_id`, its
 * functions come from the competence ROWS on `employment_product_lines`
 * (EmploymentProfile::productLines()), so every behaviour of the column is
 * AGGREGATED over a to-many relation:
 *
 *  - CELL: the DISTINCT function names of the user's rows, comma-joined
 *    (AC-017) — same treatment as the opportunities/projects/campaigns
 *    grids, plus a deterministic alphabetical order; null when the user has
 *    no row at all.
 *  - FILTER (set): matches users owning AT LEAST ONE row with one of the
 *    picked names (AC-018), via a nested `whereHas` on an allow-listed
 *    relation path — never raw SQL built from input (backend.md §8).
 *  - SORT: unlike the aggregated columns of the opportunities grid, this one
 *    stays sortable (the column catalogue declares `sortable: true`): a
 *    subquery correlated to `employment_profiles.user_id` yields the
 *    alphabetically FIRST function name of the user (AC-019). Users with no
 *    row sort NULL and therefore never drop out of the page.
 *
 * Extracted out of UserEmploymentColumns (file-size split, engineering.md
 * §6) exactly like UserOperationalSiteColumn: the aggregated shape no longer
 * fits that file's single-hop RELATED_NAME_COLUMNS machinery.
 */
final class UserBusinessFunctionColumn
{
    private const string LINES_TABLE = 'employment_product_lines';

    private const string PROFILES_TABLE = 'employment_profiles';

    private const string FUNCTIONS_TABLE = 'business_functions';

    /**
     * Maximum number of names honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The `employment.productLines.businessFunction` relation path, shared by
     * the eager load of UsersTableDefinition::baseQuery() and the whereHas
     * below — an allow-listed constant, never assembled from request input.
     */
    public const string RELATION_PATH = 'employment.productLines.businessFunction';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * The DISTINCT function names of the user's competence rows, comma-joined
     * in alphabetical order (AC-017), or null when there is none. Reads entirely off the
     * eager-loaded `employment.productLines.businessFunction` relation
     * (UsersTableDefinition::baseQuery) — never queries.
     */
    public function label(?EmploymentProfile $employment): ?string
    {
        if ($employment === null) {
            return null;
        }

        // Alphabetical, not row order: the rows come back in whatever order
        // the engine's plan yields (the unique index on
        // `employment_product_lines` is enough for SQLite to reorder them),
        // and the cell must not flip between two renders of the same data.
        // It also matches what the sort below orders on (the first name).
        $names = $employment->productLines
            ->pluck('businessFunction')
            ->filter()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
     * Set filter on the aggregated column: a user matches when AT LEAST ONE
     * of their rows points at a picked function (AC-018). Bound values,
     * capped cardinality, nested `whereHas` on a constant relation path.
     *
     * @param  Builder<Model>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $names = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        if ($names === []) {
            return;
        }

        $query->whereHas(self::RELATION_PATH, static function (Builder $functionQuery) use ($names): void {
            $functionQuery->whereIn('name', $names);
        });
    }

    /**
     * ORDER BY the alphabetically first function name among the user's rows
     * (AC-019), via a subquery correlated to `employment_profiles.user_id`:
     * the ORDER BY + LIMIT 1 picks the minimum without any aggregate raw
     * fragment, and a user with no row simply yields NULL instead of being
     * filtered out.
     *
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return EmploymentProfile::query()
            ->select(self::FUNCTIONS_TABLE.'.name')
            ->join(self::LINES_TABLE, self::LINES_TABLE.'.employment_profile_id', '=', self::PROFILES_TABLE.'.id')
            ->join(self::FUNCTIONS_TABLE, self::FUNCTIONS_TABLE.'.id', '=', self::LINES_TABLE.'.business_function_id')
            ->whereColumn(self::PROFILES_TABLE.'.user_id', 'users.id')
            ->orderBy(self::FUNCTIONS_TABLE.'.name')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the function names present
     * on the rows of the users matching $query (which already carries every
     * OTHER active filter), search-narrowed, sorted, capped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('users.id');

        return DB::table(self::LINES_TABLE)
            ->join(self::PROFILES_TABLE, self::PROFILES_TABLE.'.id', '=', self::LINES_TABLE.'.employment_profile_id')
            ->join(self::FUNCTIONS_TABLE, self::FUNCTIONS_TABLE.'.id', '=', self::LINES_TABLE.'.business_function_id')
            ->whereIn(self::PROFILES_TABLE.'.user_id', $userIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::FUNCTIONS_TABLE.'.name', 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::FUNCTIONS_TABLE.'.name')
            ->limit($limit)
            ->pluck(self::FUNCTIONS_TABLE.'.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();
    }
}
