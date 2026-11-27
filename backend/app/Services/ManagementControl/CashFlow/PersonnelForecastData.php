<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\CashFlow;

use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The imported personnel data the forecast starts from (spec 0432, D-2): a
 * constant number of aggregate queries, never one per person. Rows are keyed
 * "companyId:userId"; amounts are integer cents; periods are "YYYY-MM".
 */
final class PersonnelForecastData
{
    private function regularType(): string
    {
        return (string) config('payroll.forecast.regular_payslip_type');
    }

    /**
     * The latest period present both in the costs and in the regular payslips.
     */
    public function basePeriod(): ?string
    {
        $period = DB::table('payslips')
            ->join('payroll_costs', 'payroll_costs.period', '=', 'payslips.period')
            ->where('payslips.payslip_type', $this->regularType())
            ->max('payslips.period');

        return $period === null ? null : (string) $period;
    }

    /**
     * The people with a cost and a regular payslip in the base period.
     *
     * @param  array<int, int>  $companyIds
     * @return array<string, array{company_id: int, cost: int, net: int, terminated: ?string}>
     */
    public function people(string $base, array $companyIds): array
    {
        $rows = DB::table('payroll_costs')
            ->join('payslips', fn ($join) => $join
                ->on('payslips.company_id', '=', 'payroll_costs.company_id')
                ->on('payslips.user_id', '=', 'payroll_costs.user_id')
                ->on('payslips.period', '=', 'payroll_costs.period')
                ->where('payslips.payslip_type', $this->regularType()))
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'payslips.purchase_invoice_id')
            ->leftJoin('employment_profiles', 'employment_profiles.user_id', '=', 'payroll_costs.user_id')
            ->where('payroll_costs.period', $base)
            ->when($companyIds !== [], fn (Builder $q) => $q->whereIn('payroll_costs.company_id', $companyIds))
            ->groupBy('payroll_costs.company_id', 'payroll_costs.user_id', 'payroll_costs.total_cost', 'payroll_costs.terminated_on', 'employment_profiles.terminated_at')
            ->selectRaw('payroll_costs.company_id, payroll_costs.user_id, payroll_costs.total_cost, payroll_costs.terminated_on, employment_profiles.terminated_at AS profile_terminated_at, SUM(purchase_invoices.total_amount) AS net')
            ->get();

        $people = [];
        foreach ($rows as $row) {
            $people[$row->company_id.':'.$row->user_id] = [
                'company_id' => (int) $row->company_id,
                'cost' => ExposureSupport::cents($row->total_cost),
                'net' => ExposureSupport::cents($row->net),
                'terminated' => self::earliest([$row->terminated_on, $row->profile_terminated_at]),
            ];
        }

        return $people;
    }

    /**
     * The most frequent due day of the regular payslip installments of the base period.
     */
    public function salaryDay(string $base): int
    {
        $day = DB::table('purchase_invoice_installments')
            ->join('payslips', 'payslips.purchase_invoice_id', '=', 'purchase_invoice_installments.purchase_invoice_id')
            ->where('payslips.period', $base)
            ->where('payslips.payslip_type', $this->regularType())
            ->selectRaw('SUBSTR(purchase_invoice_installments.due_date, 9, 2) AS due_day, COUNT(*) AS occurrences')
            ->groupBy('due_day')
            ->orderByDesc('occurrences')
            ->orderBy('due_day')
            ->value('due_day');

        return $day === null ? (int) config('payroll.forecast.salary_day') : (int) $day;
    }

    /**
     * What the files already say from the base period on: real costs and nets
     * by period, non-regular payslips by person and period, earliest terminations.
     *
     * @param  array<int, int>  $companyIds
     * @return array{costs: array<string, array<string, int>>, nets: array<string, array<string, int>>, extras: array<string, true>, terminated: array<string, string>}
     */
    public function later(string $base, array $companyIds): array
    {
        $scope = fn (Builder $query, string $column): Builder => $query->when($companyIds !== [], fn (Builder $q) => $q->whereIn($column, $companyIds));

        $costs = [];
        $terminated = [];
        $costRows = $scope(DB::table('payroll_costs')->where('period', '>', $base), 'company_id')
            ->get(['company_id', 'user_id', 'period', 'total_cost', 'terminated_on']);
        foreach ($costRows as $row) {
            $key = $row->company_id.':'.$row->user_id;
            $costs[$key][(string) $row->period] = ExposureSupport::cents($row->total_cost);
            if ($row->terminated_on !== null) {
                $terminated[$key] = (string) self::earliest([$terminated[$key] ?? null, $row->terminated_on]);
            }
        }

        $nets = [];
        $netRows = $scope(
            DB::table('payslips')
                ->join('purchase_invoices', 'purchase_invoices.id', '=', 'payslips.purchase_invoice_id')
                ->where('payslips.period', '>', $base)
                ->where('payslips.payslip_type', $this->regularType()),
            'payslips.company_id',
        )->get(['payslips.company_id', 'payslips.user_id', 'payslips.period', 'purchase_invoices.total_amount']);
        foreach ($netRows as $row) {
            $nets[$row->company_id.':'.$row->user_id][(string) $row->period] = ExposureSupport::cents($row->total_amount);
        }

        $extras = [];
        $extraRows = $scope(DB::table('payslips')->where('period', '>=', $base)->where('payslip_type', '!=', $this->regularType()), 'company_id')
            ->get(['company_id', 'user_id', 'period']);
        foreach ($extraRows as $row) {
            $extras[$row->company_id.':'.$row->user_id.':'.$row->period] = true;
        }

        return ['costs' => $costs, 'nets' => $nets, 'extras' => $extras, 'terminated' => $terminated];
    }

    /**
     * @param  array<int, mixed>  $dates
     */
    public static function earliest(array $dates): ?string
    {
        $dates = array_map(fn (mixed $date): string => substr((string) $date, 0, 10), array_filter($dates, fn (mixed $date): bool => $date !== null));

        return $dates === [] ? null : min($dates);
    }
}
