<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Balances of many people at once for the HR overview (spec 0304 D-3, D-12).
 * Allowances, employment profiles and request totals are loaded in bulk for
 * the people handed in, so the number of queries does not grow with them; the
 * row logic itself is AbsenceBalanceBuilder::compose().
 *
 * @phpstan-type OverviewFilters array{business_function_id?: int|null, operational_site_id?: int|null, search?: string|null}
 */
final class AbsenceBalanceOverviewBuilder
{
    public function __construct(private readonly AbsenceBalanceBuilder $balances) {}

    /**
     * Active balance-deducting types, in catalogue order.
     *
     * @return Collection<int, AbsenceType>
     */
    public function types(): Collection
    {
        return AbsenceType::query()
            ->where('is_active', true)
            ->where('deducts_balance', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Active people with an employment profile, by name.
     *
     * @param  OverviewFilters  $filters
     * @return Builder<User>
     */
    public function people(array $filters): Builder
    {
        $query = User::query()
            ->select(['users.id', 'users.name'])
            ->where('users.is_active', true)
            ->whereHas('employment')
            ->orderBy('users.name')
            ->orderBy('users.id');

        if (! empty($filters['business_function_id'])) {
            $query->whereHas('businessFunctions', static fn (Builder $functions) => $functions->whereKey($filters['business_function_id']));
        }

        if (! empty($filters['operational_site_id'])) {
            $query->whereHas('employment.operationalSites', static fn (Builder $sites) => $sites->whereKey($filters['operational_site_id']));
        }

        if (! empty($filters['search'])) {
            $term = addcslashes((string) $filters['search'], '\\%_');
            $query->where('users.name', 'like', "%{$term}%");
        }

        return $query;
    }

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, AbsenceType>  $types
     * @return list<array{user: array{id: int, name: string}, daily_minutes: int, balances: list<array<string, mixed>>}>
     */
    public function rowsFor(Collection $users, Collection $types, int $year): array
    {
        // Step 1: bulk loads, one query each
        $userIds = $users->pluck('id')->all();
        $profiles = EmploymentProfile::query()->whereIn('user_id', $userIds)->get()->keyBy('user_id');
        $allowances = AbsenceAllowance::query()
            ->whereIn('user_id', $userIds)
            ->whereIn('absence_type_id', $types->pluck('id')->all())
            ->where('year', $year)
            ->get()
            ->keyBy(static fn (AbsenceAllowance $allowance): string => "{$allowance->user_id}.{$allowance->absence_type_id}");
        $totals = $this->requestTotals($userIds, $types->pluck('id')->all(), $year);

        // Step 2: compose one row per person and type
        return $users->map(function (User $user) use ($types, $profiles, $allowances, $totals, $year): array {
            $profile = $profiles->get($user->id);
            $rows = $types->map(fn (AbsenceType $type): array => $this->balances->compose(
                $type,
                $allowances->get("{$user->id}.{$type->id}"),
                $profile,
                $year,
                null,
                $totals["{$user->id}.{$type->id}.".AbsenceRequestStatus::Approved->value] ?? 0,
                $totals["{$user->id}.{$type->id}.".AbsenceRequestStatus::Submitted->value] ?? 0,
            ))->all();

            return $this->person($user, $profile, $rows);
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{user: array{id: int, name: string}, daily_minutes: int, balances: list<array<string, mixed>>}
     */
    private function person(User $user, ?EmploymentProfile $profile, array $rows): array
    {
        return [
            'user' => ['id' => $user->id, 'name' => $user->name],
            'daily_minutes' => $this->balances->dailyMinutes($profile),
            'balances' => $rows,
        ];
    }

    /**
     * Approved and submitted minutes keyed "user.type.status".
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $typeIds
     * @return array<string, int>
     */
    private function requestTotals(array $userIds, array $typeIds, int $year): array
    {
        $totals = [];

        AbsenceRequest::query()
            ->selectRaw('user_id, absence_type_id, status, SUM(minutes) as total')
            ->whereIn('user_id', $userIds)
            ->whereIn('absence_type_id', $typeIds)
            ->whereIn('status', [AbsenceRequestStatus::Approved->value, AbsenceRequestStatus::Submitted->value])
            ->whereBetween('starts_on', ["{$year}-01-01", "{$year}-12-31"])
            ->groupBy('user_id', 'absence_type_id', 'status')
            ->toBase()
            ->get()
            ->each(function (object $row) use (&$totals): void {
                $totals["{$row->user_id}.{$row->absence_type_id}.{$row->status}"] = (int) $row->total;
            });

        return $totals;
    }
}
