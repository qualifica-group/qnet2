<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\Models\EmploymentContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The internal labor cost (spec 0272, D-2): the time entries valued with the
 * hourly cost of their user, taken from the contract condition in force on
 * the entry date (spec 0277 D-1/D-3). One aggregate query, whatever the
 * commesse asked for, plus one for the rates of the conditions it met. The
 * hours with no condition in force, or one without salary, count in
 * `minutes` and, apart, in `unpriced_minutes`, with zero cost. The cost of an
 * aggregation row is rounded once to the cent, from the exact sum of
 * minutes x hourly cost.
 */
final class LaborCostSource
{
    private const int MINUTES_PER_HOUR = 60;

    /**
     * @param  array<int, int>  $workOrderIds
     * @return array<int, array{cents: int, minutes: int, unpriced_minutes: int}> by work order id
     */
    public function byWorkOrder(array $workOrderIds): array
    {
        if ($workOrderIds === []) {
            return [];
        }

        $rows = $this->query()->whereIn('time_entries.work_order_id', $workOrderIds)
            ->selectRaw('time_entries.work_order_id AS group_work_order_id, SUM(time_entries.minutes) AS total_minutes')
            ->groupBy('group_work_order_id', 'contract_id')
            ->get();
        $rates = $this->rates($rows);

        $byWorkOrder = [];
        foreach ($rows as $row) {
            $key = (int) $row->group_work_order_id;
            $byWorkOrder[$key] = $this->add($byWorkOrder[$key] ?? $this->empty(), $row, $rates);
        }

        return array_map($this->closed(...), $byWorkOrder);
    }

    /**
     * The entries dated in [$from, $until), by month of the date and commessa (null = none). With companies
     * given, only the hours of users whose employment profile belongs to one of them (no profile or company: out).
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array{month: string, work_order_id: int|null, cents: int, minutes: int, unpriced_minutes: int}>
     */
    public function byMonth(string $from, string $until, array $companyIds = []): array
    {
        $rows = $this->query()->where('time_entries.date', '>=', $from)->where('time_entries.date', '<', $until)
            ->when($companyIds !== [], fn (Builder $q) => $q->whereIn('employment_profiles.company_id', $companyIds))
            ->selectRaw(
                'SUBSTR(time_entries.date, 1, 7) AS group_month, time_entries.work_order_id AS group_work_order_id, '
                .'SUM(time_entries.minutes) AS total_minutes',
            )
            ->groupBy('group_month', 'group_work_order_id', 'contract_id')
            ->get();
        $rates = $this->rates($rows);

        $grouped = [];
        foreach ($rows as $row) {
            $workOrderId = $row->group_work_order_id === null ? null : (int) $row->group_work_order_id;
            $key = $row->group_month.'|'.($workOrderId ?? '');
            $grouped[$key] = $this->add($grouped[$key] ?? [...$this->empty(), 'month' => (string) $row->group_month, 'work_order_id' => $workOrderId], $row, $rates);
        }

        return array_values(array_map($this->closed(...), $grouped));
    }

    private function query(): Builder
    {
        return DB::table('time_entries')
            ->leftJoin('employment_profiles', 'employment_profiles.user_id', '=', 'time_entries.user_id')
            ->selectSub($this->contractInForce(), 'contract_id');
    }

    /** The condition in force on the entry date: the latest one starting on or before it (spec 0277 D-3). */
    private function contractInForce(): Builder
    {
        return DB::table('employment_contracts')
            ->select('employment_contracts.id')
            ->whereColumn('employment_contracts.employment_profile_id', 'employment_profiles.id')
            ->whereColumn('employment_contracts.starts_on', '<=', 'time_entries.date')
            ->orderByDesc('employment_contracts.starts_on')
            ->limit(1);
    }

    /**
     * The hourly cost of every condition the aggregate rows met, null when it has no salary.
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, string|null> by contract id
     */
    private function rates(Collection $rows): array
    {
        $ids = $rows->pluck('contract_id')->filter()->unique()->values()->all();

        return EmploymentContract::query()->whereIn('id', $ids)->get(['id', 'salary_amount', 'salary_period'])
            ->mapWithKeys(static fn (EmploymentContract $contract): array => [$contract->id => $contract->hourlyCost()])
            ->all();
    }

    /**
     * @return array{weighted: int, minutes: int, unpriced_minutes: int}
     */
    private function empty(): array
    {
        return ['weighted' => 0, 'minutes' => 0, 'unpriced_minutes' => 0];
    }

    /**
     * @param  array<string, mixed>  $total
     * @param  array<int, string|null>  $rates
     * @return array<string, mixed>
     */
    private function add(array $total, object $row, array $rates): array
    {
        $minutes = (int) $row->total_minutes;
        $total['minutes'] += $minutes;
        $rate = $row->contract_id === null ? null : ($rates[(int) $row->contract_id] ?? null);

        if ($rate === null) {
            $total['unpriced_minutes'] += $minutes;
        } else {
            $total['weighted'] += $minutes * (int) round((float) $rate * 100);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $total
     * @return array<string, mixed>
     */
    private function closed(array $total): array
    {
        $cents = (int) round($total['weighted'] / self::MINUTES_PER_HOUR);
        unset($total['weighted']);

        return ['cents' => $cents, ...$total];
    }
}
