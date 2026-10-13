<?php

namespace App\Tables\CommissionSettlementRuns;

use App\Models\CommissionSettlementRun;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Every SQL fragment the `commission-settlement-runs` table uses beyond plain
 * column references (spec 0213): the company and the skipped count live inside
 * JSON columns, read with the driver's own JSON functions. Constants of this
 * class are the only text that reaches ORDER BY / WHERE as raw SQL.
 */
final class CommissionSettlementRunSql
{
    /** Plain columns of the joined query by column id (qualified: the joins repeat names). */
    public const array PLAIN_COLUMNS = [
        'id' => 'commission_settlement_runs.id',
        'created_at' => 'commission_settlement_runs.created_at',
        'period_from' => 'commission_settlement_runs.period_from',
        'period_to' => 'commission_settlement_runs.period_to',
        'settlements_count' => 'commission_settlement_runs.settlements_count',
        'company' => 'companies.denomination',
        'created_by' => 'users.name',
    ];

    /**
     * The company the run was restricted to (filters.company_id), NULL when it covered all of them.
     *
     * @param  Builder<CommissionSettlementRun>  $query
     * @return Builder<CommissionSettlementRun>
     */
    public static function withJoins(Builder $query): Builder
    {
        return $query
            ->leftJoin('companies', DB::raw(self::companyId()), '=', 'companies.id')
            ->leftJoin('users', 'users.id', '=', 'commission_settlement_runs.created_by');
    }

    /** The company id stored in the filters JSON, as an integer or NULL. */
    public static function companyId(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(json_extract(commission_settlement_runs.filters, '$.company_id') AS INTEGER)"
            : "CAST(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(commission_settlement_runs.filters, '$.company_id')), 'null') AS UNSIGNED)";
    }

    /** Number of skipped (referent, company) pairs: the length of the skipped JSON array. */
    public static function skippedCount(): string
    {
        $length = DB::getDriverName() === 'sqlite'
            ? 'json_array_length(commission_settlement_runs.skipped)'
            : 'JSON_LENGTH(commission_settlement_runs.skipped)';

        return "COALESCE({$length}, 0)";
    }
}
