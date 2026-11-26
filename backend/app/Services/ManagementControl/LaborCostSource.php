<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\Models\EmploymentContract;
use App\Services\ManagementControl\Payroll\PayrollLaborCost;
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
 * minutes x hourly cost. The months with imported payroll costs are valued
 * with the real cost instead (spec 0430, see PayrollLaborCost): their hours
 * are never unpriced.
 */
final class LaborCostSource
{
    private const int MINUTES_PER_HOUR = 60;

    public function __construct(private readonly PayrollLaborCost $payroll) {}

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
        foreach ($this->payroll->forWorkOrders($workOrderIds) as $share) {
            $key = (int) $share['work_order_id'];
            $byWorkOrder[$key] = $this->addPayroll($byWorkOrder[$key] ?? $this->empty(), $share);
        }

        return array_map($this->closed(...), $byWorkOrder);
    }

    /**
     * The entries dated in [$from, $until), by month of the date and commessa (null = none); the entries of no
     * commessa also by user (spec 0282), null on the others. With companies given, only the hours of users whose
     * employment profile belongs to one of them (no profile or company: out).
     *
     * @param  array<int, int>  $companyIds
     * @return array<int, array{month: string, work_order_id: int|null, user_id: int|null, cents: int, minutes: int, unpriced_minutes: int}>
     */
    public function byMonth(string $from, string $until, array $companyIds = []): array
    {
        $rows = $this->query()->where('time_entries.date', '>=', $from)->where('time_entries.date', '<', $until)
            ->when($companyIds !== [], fn (Builder $q) => $q->whereIn('employment_profiles.company_id', $companyIds))
            ->selectRaw(
                'SUBSTR(time_entries.date, 1, 7) AS group_month, time_entries.work_order_id AS group_work_order_id, '
                .'CASE WHEN time_entries.work_order_id IS NULL THEN time_entries.user_id END AS group_user_id, '
                .'SUM(time_entries.minutes) AS total_minutes',
            )
            ->groupBy('group_month', 'group_work_order_id', 'group_user_id', 'contract_id')
            ->get();
        $rates = $this->rates($rows);

        $grouped = [];
        foreach ($rows as $row) {
            $workOrderId = $row->group_work_order_id === null ? null : (int) $row->group_work_order_id;
            $userId = $row->group_user_id === null ? null : (int) $row->group_user_id;
            $key = $row->group_month.'|'.($workOrderId ?? '').'|'.($userId ?? '');
            $grouped[$key] = $this->add(
                $grouped[$key] ?? [...$this->empty(), 'month' => (string) $row->group_month, 'work_order_id' => $workOrderId, 'user_id' => $userId],
                $row,
                $rates,
            );
        }

        $shares = [...$this->payroll->forRange($from, $until, $companyIds), ...$this->payroll->withoutHours($from, $until, $companyIds)];
        foreach ($shares as $share) {
            $userId = $share['work_order_id'] === null ? $share['user_id'] : null;
            $key = $share['month'].'|'.($share['work_order_id'] ?? '').'|'.($userId ?? '');
            $grouped[$key] = $this->addPayroll(
                $grouped[$key] ?? [...$this->empty(), 'month' => $share['month'], 'work_order_id' => $share['work_order_id'], 'user_id' => $userId],
                $share,
            );
        }

        return array_values(array_map($this->closed(...), $grouped));
    }

    private function query(): Builder
    {
        return DB::table('time_entries')
            ->leftJoin('employment_profiles', 'employment_profiles.user_id', '=', 'time_entries.user_id')
            ->whereNotExists($this->payrollMonth())
            ->selectSub($this->contractInForce(), 'contract_id');
    }

    /** The entry falls in a month of its user with imported payroll costs: valued by PayrollLaborCost, not here. */
    private function payrollMonth(): Builder
    {
        return DB::table('payroll_costs')
            ->selectRaw('1')
            ->whereColumn('payroll_costs.user_id', 'time_entries.user_id')
            ->whereRaw('payroll_costs.period = SUBSTR(time_entries.date, 1, 7)');
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
     * @return array{weighted: int, payroll: float, minutes: int, unpriced_minutes: int}
     */
    private function empty(): array
    {
        return ['weighted' => 0, 'payroll' => 0.0, 'minutes' => 0, 'unpriced_minutes' => 0];
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
     * @param  array{minutes: int, cost: float}  $share
     * @return array<string, mixed>
     */
    private function addPayroll(array $total, array $share): array
    {
        $total['minutes'] += $share['minutes'];
        $total['payroll'] += $share['cost'];

        return $total;
    }

    /**
     * @param  array<string, mixed>  $total
     * @return array<string, mixed>
     */
    private function closed(array $total): array
    {
        $cents = (int) round($total['weighted'] / self::MINUTES_PER_HOUR + $total['payroll']);
        unset($total['weighted'], $total['payroll']);

        return ['cents' => $cents, ...$total];
    }
}
