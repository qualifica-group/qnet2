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
 * The `managed_business_functions` grid column on `users` (spec 0276 D-3): the
 * business functions the person is manager of (`business_functions.manager_id`,
 * User::managedBusinessFunctions()). Modelled on UserBusinessFunctionColumn,
 * every behaviour is AGGREGATED over that to-many relation:
 *
 *  - CELL: the function names, comma-joined alphabetically; null when none.
 *  - FILTER (set): matches users managing AT LEAST ONE picked function, via a
 *    `whereHas` on an allow-listed relation path (backend.md §8).
 *  - SORT: a subquery correlated to `users.id` yields the alphabetically
 *    FIRST managed function name; users managing none sort NULL.
 */
final class UserManagedBusinessFunctionsColumn
{
    use HandlesBlankSetFilter;

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
    public const string RELATION_PATH = 'managedBusinessFunctions';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Reads off the eager-loaded `managedBusinessFunctions` relation.
     */
    public function label(User $user): ?string
    {
        $names = $user->managedBusinessFunctions->pluck('name')->sort()->values();

        return $names->isEmpty() ? null : $names->implode(', ');
    }

    /**
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

            // The blank entry ("(Vuoti)"): manages no function at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave(self::RELATION_PATH);
            }
        });
    }

    /**
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return BusinessFunction::query()
            ->select(self::FUNCTIONS_TABLE.'.name')
            ->whereColumn(self::FUNCTIONS_TABLE.'.manager_id', 'users.id')
            ->orderBy(self::FUNCTIONS_TABLE.'.name')
            ->limit(1);
    }

    /**
     * Excel-like distinct values: the managed function names of the users
     * matching $query (which already carries every OTHER active filter).
     *
     * @param  Builder<Model>  $query
     * @return array<int, string>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('users.id');

        $values = DB::table(self::FUNCTIONS_TABLE)
            ->whereIn(self::FUNCTIONS_TABLE.'.manager_id', $userIds)
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
