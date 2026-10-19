<?php

namespace App\Tables\Users;

use App\Models\EmploymentProfile;
use App\Services\Table\FilterApplier;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The `job_roles` grid column on `users` (spec 0271 AC-012, replacing the
 * former free-text `job_description`): the person's job roles live on the
 * `employment_profile_job_role` pivot, so the column is AGGREGATED over a
 * to-many relation exactly like UserBusinessFunctionColumn.
 *
 *  - CELL: the job role names, comma-joined in alphabetical order, null when
 *    the person has none.
 *  - FILTER (set): at least one picked name, via a nested `whereHas` on the
 *    constant relation path below (never raw SQL from input).
 *  - SORT: the alphabetically first name, via a correlated subquery.
 */
final class UserJobRolesColumn
{
    use HandlesBlankSetFilter;

    private const string PIVOT_TABLE = 'employment_profile_job_role';

    private const string PROFILES_TABLE = 'employment_profiles';

    private const string ROLES_TABLE = 'job_roles';

    private const int MAX_FILTER_VALUES = 200;

    /** Allow-listed relation path shared by the eager load and the whereHas. */
    public const string RELATION_PATH = 'employment.jobRoles';

    public function __construct(private readonly FilterApplier $filterApplier) {}

    /**
     * Reads off the eager-loaded `employment.jobRoles` relation — never queries.
     */
    public function label(?EmploymentProfile $employment): ?string
    {
        if ($employment === null) {
            return null;
        }

        $names = $employment->jobRoles->pluck('name')->sort()->values();

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
                $group->whereHas(self::RELATION_PATH, static function (Builder $roleQuery) use ($names): void {
                    $roleQuery->whereIn('name', $names);
                });
            }

            // The blank entry ("(Vuoti)"): no profile, or one with no job role.
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
        return EmploymentProfile::query()
            ->select(self::ROLES_TABLE.'.name')
            ->join(self::PIVOT_TABLE, self::PIVOT_TABLE.'.employment_profile_id', '=', self::PROFILES_TABLE.'.id')
            ->join(self::ROLES_TABLE, self::ROLES_TABLE.'.id', '=', self::PIVOT_TABLE.'.job_role_id')
            ->whereColumn(self::PROFILES_TABLE.'.user_id', 'users.id')
            ->orderBy(self::ROLES_TABLE.'.name')
            ->limit(1);
    }

    /**
     * @param  Builder<Model>  $query
     * @return array<int, string|null>
     */
    public function distinctValues(Builder $query, ?string $search, int $limit): array
    {
        $userIds = (clone $query)->select('users.id');

        $values = DB::table(self::PIVOT_TABLE)
            ->join(self::PROFILES_TABLE, self::PROFILES_TABLE.'.id', '=', self::PIVOT_TABLE.'.employment_profile_id')
            ->join(self::ROLES_TABLE, self::ROLES_TABLE.'.id', '=', self::PIVOT_TABLE.'.job_role_id')
            ->whereIn(self::PROFILES_TABLE.'.user_id', $userIds)
            ->when($search !== null && $search !== '', function ($builder) use ($search): void {
                $builder->where(self::ROLES_TABLE.'.name', 'like', '%'.$this->filterApplier->escapeLike($search).'%');
            })
            ->distinct()
            ->orderBy(self::ROLES_TABLE.'.name')
            ->limit($limit)
            ->pluck(self::ROLES_TABLE.'.name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return $this->withBlankEntry($values, $search, fn (): bool => (clone $query)
            ->whereDoesntHave(self::RELATION_PATH)
            ->exists());
    }
}
