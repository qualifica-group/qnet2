<?php

namespace App\Tables\Users;

use App\Models\User;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * The derived `roles` set filter and distinct values of the users grid
 * (extracted from UsersTableDefinition for its file size, engineering.md §6).
 * The assignable role catalogue (never `super-admin` for a non super-admin
 * actor) is resolved by the caller and passed in.
 */
final class UserRolesColumn
{
    use HandlesBlankSetFilter;

    /**
     * Maximum number of role names honoured in the `roles` set filter. Caps the
     * WHERE IN cardinality (defence in depth); excess values are ignored.
     */
    private const int MAX_FILTER_VALUES = 50;

    /**
     * Applies `roles` via whereHas on the Spatie role relationship. Only string
     * names, capped cardinality, bound parameters — never inlined.
     *
     * @param  Builder<User>  $query
     * @param  array<string, mixed>  $filter
     */
    public function applyFilter(Builder $query, array $filter): void
    {
        $values = $filter['values'] ?? null;

        if (! is_array($values)) {
            return;
        }

        $roles = array_slice(array_values(array_filter(
            $values,
            static fn ($value): bool => is_string($value) && $value !== '',
        )), 0, self::MAX_FILTER_VALUES);

        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($roles === [] && ! $matchesBlank) {
            return;
        }

        $query->where(static function (Builder $group) use ($roles, $matchesBlank): void {
            if ($roles !== []) {
                $group->whereHas('roles', static function (Builder $roleQuery) use ($roles): void {
                    $roleQuery->whereIn('name', $roles);
                });
            }

            // The blank entry ("(Vuoti)"): the users carrying no role at all.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('roles');
            }
        });
    }

    /**
     * @param  array<int, string>  $assignableRoles
     * @return array<int, string|null>
     */
    public function distinctValues(array $assignableRoles, ?string $search, int $limit): array
    {
        if ($search !== null && $search !== '') {
            return array_slice(array_values(array_filter(
                $assignableRoles,
                static fn (string $role): bool => stripos($role, $search) !== false,
            )), 0, $limit);
        }

        // The blank entry ("(Vuoti)") sits beside the assignable catalogue
        // (itself offered unscoped): a user may carry no role at all.
        return array_merge([null], array_slice($assignableRoles, 0, $limit));
    }
}
