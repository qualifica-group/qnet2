<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Payroll;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * The real labor cost from the imported payroll (spec 0430 D-7, D-8, D-10). For every user and month with at
 * least one payroll row, the month cost (sum of `total_cost` over all the companies, in cents) is spread over the
 * timesheet minutes of that month: each group of minutes gets cost x group minutes / month minutes, where the
 * month minutes are all the entries of the user in that month, with and without work order. The share is returned
 * unrounded (float cents): the caller rounds each aggregation row once. Every query is aggregate, so their number
 * does not depend on users or months.
 */
final class PayrollLaborCost
{
    /**
     * @param  array<int, int>  $workOrderIds
     * @return list<array{month: string, work_order_id: int|null, user_id: int, minutes: int, cost: float}>
     */
    public function forWorkOrders(array $workOrderIds): array
    {
        return $this->shares($this->valued()->whereIn('time_entries.work_order_id', $workOrderIds));
    }

    /**
     * The entries dated in [$from, $until), valued with the payroll, restricted to the users of the companies given.
     *
     * @param  array<int, int>  $companyIds
     * @return list<array{month: string, work_order_id: int|null, user_id: int, minutes: int, cost: float}>
     */
    public function forRange(string $from, string $until, array $companyIds = []): array
    {
        $query = $this->valued()->where('time_entries.date', '>=', $from)->where('time_entries.date', '<', $until);

        return $this->shares($this->companyFiltered($query, $companyIds));
    }

    /**
     * The cost of the users with payroll in a month of the range and no minute at all in it (D-8), by user and
     * month, as rows with no work order and zero minutes. A month is in the range when its first day is.
     *
     * @param  array<int, int>  $companyIds
     * @return list<array{month: string, work_order_id: null, user_id: int, minutes: int, cost: float}>
     */
    public function withoutHours(string $from, string $until, array $companyIds = []): array
    {
        $first = CarbonImmutable::parse($from);
        $first = $first->day === 1 ? $first : $first->addMonthNoOverflow()->startOfMonth();
        $last = CarbonImmutable::parse($until)->subDay()->format('Y-m');

        $query = DB::table('payroll_costs')
            ->leftJoin('employment_profiles', 'employment_profiles.user_id', '=', 'payroll_costs.user_id')
            ->where('payroll_costs.period', '>=', $first->format('Y-m'))
            ->where('payroll_costs.period', '<=', $last)
            ->whereNotExists(fn (Builder $exists) => $exists->selectRaw('1')->from('time_entries')
                ->whereColumn('time_entries.user_id', 'payroll_costs.user_id')
                ->whereRaw('SUBSTR(time_entries.date, 1, 7) = payroll_costs.period'))
            ->selectRaw('payroll_costs.user_id AS group_user_id, payroll_costs.period AS group_month, ROUND(SUM(payroll_costs.total_cost) * 100) AS cost_cents')
            ->groupBy('group_user_id', 'group_month');

        return $this->companyFiltered($query, $companyIds)->get()->map(static fn (object $row): array => [
            'month' => (string) $row->group_month,
            'work_order_id' => null,
            'user_id' => (int) $row->group_user_id,
            'minutes' => 0,
            'cost' => (float) $row->cost_cents,
        ])->all();
    }

    /** The entries of the (user, month) pairs that have payroll, with the cost and the minutes of their month. */
    private function valued(): Builder
    {
        $pairMatch = static fn (JoinClause $join, string $alias) => $join
            ->on("{$alias}.user_id", '=', 'time_entries.user_id')
            ->whereRaw("{$alias}.period = SUBSTR(time_entries.date, 1, 7)");

        $payroll = DB::table('payroll_costs')
            ->selectRaw('user_id, period, ROUND(SUM(total_cost) * 100) AS cost_cents')
            ->groupBy('user_id', 'period');
        $monthMinutes = DB::table('time_entries')
            ->selectRaw('user_id, SUBSTR(date, 1, 7) AS period, SUM(minutes) AS month_minutes')
            ->whereIn('user_id', DB::table('payroll_costs')->select('user_id'))
            ->groupBy('user_id', 'period');

        return DB::table('time_entries')
            ->joinSub($payroll, 'payroll', fn (JoinClause $join) => $pairMatch($join, 'payroll'))
            ->joinSub($monthMinutes, 'month_total', fn (JoinClause $join) => $pairMatch($join, 'month_total'))
            ->leftJoin('employment_profiles', 'employment_profiles.user_id', '=', 'time_entries.user_id');
    }

    /**
     * @param  array<int, int>  $companyIds
     */
    private function companyFiltered(Builder $query, array $companyIds): Builder
    {
        return $query->when($companyIds !== [], fn (Builder $q) => $q->whereIn('employment_profiles.company_id', $companyIds));
    }

    /**
     * @return list<array{month: string, work_order_id: int|null, user_id: int, minutes: int, cost: float}>
     */
    private function shares(Builder $query): array
    {
        return $query
            ->selectRaw(
                'SUBSTR(time_entries.date, 1, 7) AS group_month, time_entries.work_order_id AS group_work_order_id, '
                .'time_entries.user_id AS group_user_id, SUM(time_entries.minutes) AS total_minutes, '
                .'payroll.cost_cents AS cost_cents, month_total.month_minutes AS month_minutes',
            )
            ->groupBy('group_month', 'group_work_order_id', 'group_user_id', 'payroll.cost_cents', 'month_total.month_minutes')
            ->get()
            ->map(static fn (object $row): array => [
                'month' => (string) $row->group_month,
                'work_order_id' => $row->group_work_order_id === null ? null : (int) $row->group_work_order_id,
                'user_id' => (int) $row->group_user_id,
                'minutes' => (int) $row->total_minutes,
                'cost' => (float) $row->cost_cents * (int) $row->total_minutes / (int) $row->month_minutes,
            ])
            ->all();
    }
}
