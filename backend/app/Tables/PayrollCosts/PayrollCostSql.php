<?php

namespace App\Tables\PayrollCosts;

use App\Models\PayrollCost;
use Illuminate\Database\Eloquent\Builder;

/**
 * Joined query and qualified column map of the `payroll-costs` table: the constants of this class are the only
 * text that reaches ORDER BY / WHERE as a column reference (never request input).
 */
final class PayrollCostSql
{
    /** Column id to qualified SQL column (the joins repeat names such as `id`). */
    public const array PLAIN_COLUMNS = [
        'id' => 'payroll_costs.id',
        'period' => 'payroll_costs.period',
        'company' => 'companies.denomination',
        'user' => 'users.name',
        'tax_code' => 'payroll_costs.tax_code',
        'salary_cost' => 'payroll_costs.salary_cost',
        'contribution_cost' => 'payroll_costs.contribution_cost',
        'insurance_cost' => 'payroll_costs.insurance_cost',
        'total_cost' => 'payroll_costs.total_cost',
        'ordinary_hours' => 'payroll_costs.ordinary_hours',
        'hired_on' => 'payroll_costs.hired_on',
        'terminated_on' => 'payroll_costs.terminated_on',
    ];

    /** Columns filtered and listed as a set over the joined expression. */
    public const array SET_COLUMNS = ['period', 'company'];

    /**
     * @param  Builder<PayrollCost>  $query
     * @return Builder<PayrollCost>
     */
    public static function withJoins(Builder $query): Builder
    {
        return $query
            ->join('companies', 'companies.id', '=', 'payroll_costs.company_id')
            ->join('users', 'users.id', '=', 'payroll_costs.user_id')
            ->select('payroll_costs.*')
            ->selectRaw('companies.denomination as company_name, users.name as user_name');
    }
}
