<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Builds the balance rows of a person for a year (spec 0278 D-1, D-10).
 *
 * A request counts entirely in the year of its `starts_on`: a request that
 * straddles New Year is not split between two years (one simple, predictable
 * rule; the minutes are frozen at submission anyway, D-6).
 *
 * @phpstan-type BalanceRow array{absence_type: array{id: int, name: string}, annual_minutes: int, carried_over_minutes: int, accrued_minutes: int, used_minutes: int, pending_minutes: int, residual_minutes: int}
 */
final class AbsenceBalanceBuilder
{
    public function __construct(private readonly AbsenceAccrualCalculator $calculator) {}

    /**
     * One row per active balance-deducting type, in catalogue order.
     *
     * @return list<array<string, mixed>>
     */
    public function forUser(User $user, int $year, ?CarbonInterface $today = null): array
    {
        // Step 1: the catalogue rows that scale a balance
        $types = AbsenceType::query()
            ->where('is_active', true)
            ->where('deducts_balance', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Step 2: one row per type
        return $types->map(fn (AbsenceType $type): array => $this->row($user, $type, $year, $today))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function row(User $user, AbsenceType $type, int $year, ?CarbonInterface $today = null): array
    {
        // Step 1: allowance and employment window of the person
        $allowance = AbsenceAllowance::query()
            ->where('user_id', $user->id)
            ->where('absence_type_id', $type->id)
            ->where('year', $year)
            ->first();
        $profile = EmploymentProfile::query()->where('user_id', $user->id)->first();
        $annual = $allowance->annual_minutes ?? 0;
        $carriedOver = $allowance->carried_over_minutes ?? 0;

        // Step 2: accrual to date and request totals
        $months = $this->calculator->accruedMonths($year, $profile?->hired_at, $profile?->terminated_at, $today ?? CarbonImmutable::today());
        $accrued = $this->calculator->accruedMinutes($annual, $months);
        $used = $this->requestMinutes($user, $type, $year, AbsenceRequestStatus::Approved);
        $pending = $this->requestMinutes($user, $type, $year, AbsenceRequestStatus::Submitted);

        return [
            'absence_type' => ['id' => $type->id, 'name' => $type->name],
            'annual_minutes' => $annual,
            'carried_over_minutes' => $carriedOver,
            'accrued_minutes' => $accrued,
            'used_minutes' => $used,
            'pending_minutes' => $pending,
            'residual_minutes' => $carriedOver + $accrued - $used,
        ];
    }

    private function requestMinutes(User $user, AbsenceType $type, int $year, AbsenceRequestStatus $status): int
    {
        return (int) AbsenceRequest::query()
            ->where('user_id', $user->id)
            ->where('absence_type_id', $type->id)
            ->where('status', $status)
            ->whereBetween('starts_on', ["{$year}-01-01", "{$year}-12-31"])
            ->sum('minutes');
    }
}
