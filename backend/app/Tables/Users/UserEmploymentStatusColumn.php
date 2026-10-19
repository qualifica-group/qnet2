<?php

namespace App\Tables\Users;

use App\Models\EmploymentProfile;
use App\Tables\Concerns\HandlesBlankSetFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The `employment_status` grid column on `users` (spec 0272 D-8, AC-013):
 * `terminated` once the employment profile's termination date has arrived
 * (`terminated_at` <= today), `employed` otherwise. A user with no employment
 * profile has no status (blank cell, matched by the "(Vuoti)" entry).
 *
 * Derived from `employment_profiles.terminated_at`, so cell, filter, sort and
 * distinct values all share the same today-based rule below.
 */
final class UserEmploymentStatusColumn
{
    use HandlesBlankSetFilter;

    public const string EMPLOYED = 'employed';

    public const string TERMINATED = 'terminated';

    /** @var array<int, string> */
    public const array VALUES = [self::EMPLOYED, self::TERMINATED];

    public function label(?EmploymentProfile $employment): ?string
    {
        if ($employment === null) {
            return null;
        }

        $terminatedAt = $employment->terminated_at;

        return $terminatedAt !== null && $terminatedAt->lte(today()) ? self::TERMINATED : self::EMPLOYED;
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

        $statuses = array_values(array_intersect(self::VALUES, $values));
        $matchesBlank = $this->matchesBlankEntry($filter);

        if ($statuses === [] && ! $matchesBlank) {
            return;
        }

        $today = today()->toDateString();

        $query->where(static function (Builder $group) use ($statuses, $matchesBlank, $today): void {
            if ($statuses !== []) {
                $group->whereHas('employment', static function (Builder $profile) use ($statuses, $today): void {
                    $profile->where(static function (Builder $status) use ($statuses, $today): void {
                        if (in_array(self::EMPLOYED, $statuses, true)) {
                            $status->where(static fn (Builder $open): Builder => $open
                                ->whereNull('terminated_at')
                                ->orWhere('terminated_at', '>', $today));
                        }

                        if (in_array(self::TERMINATED, $statuses, true)) {
                            $status->orWhere('terminated_at', '<=', $today);
                        }
                    });
                });
            }

            // The blank entry ("(Vuoti)"): a user with no employment profile.
            if ($matchesBlank) {
                $group->orWhereDoesntHave('employment');
            }
        });
    }

    /**
     * @return Builder<Model>
     */
    public function sortSubquery(): Builder
    {
        return EmploymentProfile::query()
            ->selectRaw(
                'CASE WHEN employment_profiles.terminated_at IS NOT NULL AND employment_profiles.terminated_at <= ? THEN ? ELSE ? END',
                [today()->toDateString(), self::TERMINATED, self::EMPLOYED],
            )
            ->whereColumn('employment_profiles.user_id', 'users.id')
            ->limit(1);
    }

    /**
     * The fixed catalogue plus the blank entry, offered unscoped like every
     * enum column of the grid.
     *
     * @return array<int, string|null>
     */
    public function distinctValues(?string $search, int $limit): array
    {
        if ($search !== null && $search !== '') {
            return array_slice(array_values(array_filter(
                self::VALUES,
                static fn (string $value): bool => stripos($value, $search) !== false,
            )), 0, $limit);
        }

        return array_merge([null], array_slice(self::VALUES, 0, $limit));
    }
}
