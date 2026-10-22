<?php

declare(strict_types=1);

namespace App\Services\Absences\Balances;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceAllowance;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\TimeEntries\DailyTargetResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Builds the balance rows of a person for a year (spec 0278 D-1, D-10).
 *
 * A request counts entirely in the year of its `starts_on`: a request that
 * straddles New Year is not split between two years (one simple, predictable
 * rule; the minutes are frozen at submission anyway, D-6).
 *
 * @phpstan-type BalanceRow array{absence_type: array{id: int, name: string, color: string}, annual_minutes: int, source: string, carried_over_minutes: int, accrued_minutes: int, used_minutes: int, pending_minutes: int, residual_minutes: int}
 */
final class AbsenceBalanceBuilder
{
    public const string SOURCE_ALLOWANCE = 'allowance';

    public const string SOURCE_DEFAULT = 'default';

    public const string SOURCE_NONE = 'none';

    public function __construct(
        private readonly AbsenceAccrualCalculator $calculator,
        private readonly DailyTargetResolver $daily,
    ) {}

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

        // Step 2: request totals, then the pure composition
        return $this->compose(
            $type,
            $allowance,
            $profile,
            $year,
            $today,
            $this->requestMinutes($user, $type, $year, AbsenceRequestStatus::Approved),
            $this->requestMinutes($user, $type, $year, AbsenceRequestStatus::Submitted),
        );
    }

    /**
     * The row from already-loaded inputs, with no query: shared by the single
     * person path and the batch overview so the accrual logic exists once.
     *
     * @return array<string, mixed>
     */
    public function compose(
        AbsenceType $type,
        ?AbsenceAllowance $allowance,
        ?EmploymentProfile $profile,
        int $year,
        ?CarbonInterface $today,
        int $used,
        int $pending,
    ): array {
        // Step 1: effective allowance (spec 0304 D-12) and carry-over
        [$annual, $source] = $this->effectiveAnnual($type, $allowance, $profile);
        $carriedOver = $allowance->carried_over_minutes ?? 0;

        // Step 2: accrual to date
        $months = $this->calculator->accruedMonths($year, $profile?->hired_at, $profile?->terminated_at, $today ?? CarbonImmutable::today());
        $accrued = $this->calculator->accruedMinutes($annual, $months);

        return [
            'absence_type' => ['id' => $type->id, 'name' => $type->name, 'color' => $type->color],
            'annual_minutes' => $annual,
            'source' => $source,
            'carried_over_minutes' => $carriedOver,
            'accrued_minutes' => $accrued,
            'used_minutes' => $used,
            'pending_minutes' => $pending,
            'residual_minutes' => $carriedOver + $accrued - $used,
        ];
    }

    public function dailyMinutes(?EmploymentProfile $profile): int
    {
        return $this->daily->resolve($profile);
    }

    /**
     * The set allowance wins; otherwise the type default in days times the
     * person's daily minutes (part-time proportional); otherwise nothing.
     *
     * @return array{0: int, 1: string}
     */
    private function effectiveAnnual(AbsenceType $type, ?AbsenceAllowance $allowance, ?EmploymentProfile $profile): array
    {
        if ($allowance?->annual_minutes !== null) {
            return [$allowance->annual_minutes, self::SOURCE_ALLOWANCE];
        }

        if ($type->default_annual_days !== null) {
            return [(int) round((float) $type->default_annual_days * $this->daily->resolve($profile)), self::SOURCE_DEFAULT];
        }

        return [0, self::SOURCE_NONE];
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
