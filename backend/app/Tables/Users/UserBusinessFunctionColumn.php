<?php

namespace App\Tables\Users;

use App\Models\BusinessFunction;
use App\Models\User;
use App\Services\Table\FilterApplier;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `business_function` grid column on `users` (spec 0015, revised by spec
 * 0275 D-8): it shows the business functions the person BELONGS to (pivot
 * `business_function_user`, User::businessFunctions()), not their competences.
 * Every behaviour is AGGREGATED over that to-many relation:
 *
 *  - CELL: the function names, comma-joined alphabetically; null when none.
 *  - FILTER (set): matches users belonging to AT LEAST ONE picked function,
 *    via a `whereHas` on an allow-listed relation path (backend.md §8).
 *  - SORT: a subquery correlated to `users.id` yields the alphabetically
 *    FIRST function name; users with none sort NULL and never drop out.
 *
 * Extracted out of UserEmploymentColumns (file-size split, engineering.md §6).
 */
final class UserBusinessFunctionColumn
{
    use HandlesBlankSetFilter;

    private const string MEMBERSHIP_TABLE = 'business_function_user';

    private const string FUNCTIONS_TABLE = 'business_functions';

    /**
     * Maximum number of names honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The relation path shared by the eager load of UsersTableDefinition::
     * baseQuery() and the whereHas below: an allow-listed constant, never
     * assembled from request input.
     */
    public const string RELATION_PATH = 'businessFunctions';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * The function names of the user's membership, comma-joined in
     * alphabetical order, or null when none. Reads off the eager-loaded
     * `businessFunctions` relation (UsersTableDefinition::baseQuery).
     */
    public function label(User $user): ?string
    {
        $names = $user->businessFunctions->pluck('name')->sort()->values();

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

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($names === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($names, $matchesBlank): void {
            if ($names !== []) {
                $group->whereHas(self::RELATION_PATH, static function (Builder $functionQuery) use ($names): void {
                    $functionQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): no membership at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave(self::RELATION_PATH);
            }
        });
    }

    /**
     * ORDER BY the alphabetically first function name of the user's
     * membership, via a subquery correlated to `users.id`: ORDER BY + LIMIT 1
     * picks the minimum without raw fragments, and a user with no membership
     * yields NULL instead of being filtered out.
     *
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return BusinessFunction::query()
            ->select(self::FUNCTIONS_TABLE.'.name')
            ->join(self::MEMBERSHIP_TABLE, self::MEMBERSHIP_TABLE.'.business_function_id', '=', self::FUNCTIONS_TABLE.'.id')
            ->whereColumn(self::MEMBERSHIP_TABLE.'.user_id', 'users.id')
            ->orderBy(self::FUNCTIONS_TABLE.'.name')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the function names the
     * users matching $query (which already carries every
     * OTHER active filter), search-narrowed, sorted, capped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('users.id');

        $values = DB::table(self::MEMBERSHIP_TABLE)
            ->join(self::FUNCTIONS_TABLE, self::FUNCTIONS_TABLE.'.id', '=', self::MEMBERSHIP_TABLE.'.business_function_id')
            ->whereIn(self::MEMBERSHIP_TABLE.'.user_id', $userIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::FUNCTIONS_TABLE.'.name', 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::FUNCTIONS_TABLE.'.name')
            ->limit($limit)
            ->pluck(self::FUNCTIONS_TABLE.'.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave(self::RELATION_PATH)
            ->exists());
    }
}
