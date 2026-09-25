<?php

namespace App\Tables\Users;

use App\Models\EmploymentProfile;
use App\Services\Table\FilterApplier;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `reports_to` grid column on `users` (spec 0166 D-9), extracted out of
 * UserEmploymentColumns (file-size split, engineering.md §6) exactly like
 * UserBusinessFunctionColumn: a profile no longer carries a single
 * `reports_to_id` FK, its managers come from the `employment_profile_manager`
 * pivot (EmploymentProfile::reportsTo(), a BelongsToMany ordered by
 * `users.name`), so every behaviour of the column is AGGREGATED over a
 * to-many relation, mirroring WorkOrderDerivedColumns' own pivot-based
 * `supervisors` column:
 *
 *  - CELL: the manager `{id, name}` summaries in the relation's own order
 *    (already `users.name` ascending), `[]` when the user has no employment
 *    profile or no manager at all (AC-010).
 *  - FILTER (set): matches users with AT LEAST ONE manager among the picked
 *    NAMES, via a nested `whereHas` on an allow-listed relation path — never
 *    raw SQL built from input (backend.md §8). The blank entry ("(Vuoti)")
 *    matches a user with no manager at all.
 *  - SORT: a subquery correlated to `employment_profiles.user_id`, joined
 *    through the pivot to a SELF-JOIN alias of `users` (the outer query's
 *    own table), yielding the alphabetically FIRST manager name (MIN) — no
 *    row-multiplying JOIN on the main query. Users with no manager sort NULL.
 *  - DISTINCT VALUES: the manager names reachable from the filtered users via
 *    the pivot, search-narrowed, sorted, capped.
 */
final class UserReportsToColumn
{
    use HandlesBlankSetFilter;

    private const string PIVOT_TABLE = 'employment_profile_manager';

    private const string PROFILES_TABLE = 'employment_profiles';

    private const string USERS_TABLE = 'users';

    private const string MANAGER_ALIAS = 'employment_reports_to_managers';

    /**
     * Maximum number of names honoured in the set filter. Caps the WHERE IN
     * cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 200;

    /**
     * The `employment.reportsTo` relation path, shared by the eager load of
     * UsersTableDefinition::baseQuery() and the whereHas below — an
     * allow-listed constant, never assembled from request input.
     */
    public const string RELATION_PATH = 'employment.reportsTo';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * The manager `{id, name}` summaries, in the relation's own order
     * (`users.name` ascending), `[]` when the user has no employment profile
     * or no manager at all (AC-010). Reads entirely off the eager-loaded
     * `employment.reportsTo` relation (UsersTableDefinition::baseQuery) —
     * never queries.
     *
     * @return array<int, array{id: int, name: string}>
     */
    public function managers(?EmploymentProfile $employment): array
    {
        if ($employment === null) {
            return [];
        }

        return $employment->reportsTo
            ->map(static fn ($manager): array => ['id' => $manager->id, 'name' => $manager->name])
            ->values()
            ->all();
    }

    /**
     * Set filter on the aggregated column: a user matches when AT LEAST ONE
     * of their managers carries a picked name (spec 0166 D-9/AC-010). Bound
     * values, capped cardinality, nested `whereHas` on a constant relation
     * path.
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
                $group->whereHas(self::RELATION_PATH, static function (Builder $managerQuery) use ($names): void {
                    $managerQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): no employment profile, or one with
            // no manager at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave(self::RELATION_PATH);
            }
        });
    }

    /**
     * ORDER BY the alphabetically first manager name among the user's
     * managers (MIN), via a subquery correlated to
     * `employment_profiles.user_id`: the ORDER BY + LIMIT 1 picks the minimum
     * without any aggregate raw fragment, and a user with no manager simply
     * yields NULL instead of being filtered out. The pivot join needs a
     * SELF-JOIN alias of `users` (the outer query's own table).
     *
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return EmploymentProfile::query()
            ->select(self::MANAGER_ALIAS.'.name')
            ->join(self::PIVOT_TABLE, self::PIVOT_TABLE.'.employment_profile_id', '=', self::PROFILES_TABLE.'.id')
            ->join(self::USERS_TABLE.' as '.self::MANAGER_ALIAS, self::MANAGER_ALIAS.'.id', '=', self::PIVOT_TABLE.'.user_id')
            ->whereColumn(self::PROFILES_TABLE.'.user_id', 'users.id')
            ->orderBy(self::MANAGER_ALIAS.'.name')
            ->limit(1);
    }

    /**
     * Excel-like distinct values (spec 0004/0005): the manager names reachable
     * from the users matching $query (which already carries every OTHER
     * active filter) via the pivot, search-narrowed, sorted, capped.
     *
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('users.id');

        $values = DB::table(self::USERS_TABLE)
            ->join(self::PIVOT_TABLE, self::PIVOT_TABLE.'.user_id', '=', self::USERS_TABLE.'.id')
            ->join(self::PROFILES_TABLE, self::PROFILES_TABLE.'.id', '=', self::PIVOT_TABLE.'.employment_profile_id')
            ->whereIn(self::PROFILES_TABLE.'.user_id', $userIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::USERS_TABLE.'.name', 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::USERS_TABLE.'.name')
            ->limit($limit)
            ->pluck(self::USERS_TABLE.'.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave(self::RELATION_PATH)
            ->exists());
    }
}
