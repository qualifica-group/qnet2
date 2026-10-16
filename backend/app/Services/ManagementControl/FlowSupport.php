<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\Services\ManagementControl\Exposure\ExposureSupport;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query pieces shared by the cash flow and the monthly trend aggregators (spec
 * 0270). Every column name is an internal constant of the caller, never input;
 * the filter values are always bound.
 */
final class FlowSupport
{
    public const string UNATTRIBUTED = 'unattributed';

    /** Bucket of the installments already due at the snapshot day. */
    public const string BACKLOG = 'backlog';

    /**
     * Restricts the query to the companies and sites given; an empty list does not filter.
     *
     * @param  array<int, int>  $companyIds
     * @param  array<int, int>  $companySiteIds
     * @param  array<int, int>  $operationalSiteIds
     * @param  array{company: string, company_site: string, operational_site: string}  $columns
     */
    public static function scope(Builder $query, array $companyIds, array $companySiteIds, array $operationalSiteIds, array $columns): Builder
    {
        return $query
            ->when($companyIds !== [], fn (Builder $q) => $q->whereIn($columns['company'], $companyIds))
            ->when($companySiteIds !== [], fn (Builder $q) => $q->whereIn($columns['company_site'], $companySiteIds))
            ->when($operationalSiteIds !== [], fn (Builder $q) => $q->whereIn($columns['operational_site'], $operationalSiteIds));
    }

    /**
     * Open residual cents of installments by company and bucket: "backlog" when
     * the due date is before today, else the "YYYY-MM" of the due date (D-2/D-3).
     *
     * @return array<int, array<string, int>> cents by bucket by company id (0 = none)
     */
    public static function installmentCents(Builder $installments, string $dueColumn, string $residualColumn, string $companyColumn, string $today): array
    {
        $rows = $installments->where($residualColumn, '>', 0)
            ->selectRaw(
                "{$companyColumn} AS group_company_id, CASE WHEN {$dueColumn} < ? THEN '".self::BACKLOG."' ELSE SUBSTR({$dueColumn}, 1, 7) END AS bucket, SUM({$residualColumn}) AS total",
                [$today],
            )
            ->groupBy('group_company_id', 'bucket')
            ->get();

        $cents = [];
        foreach ($rows as $row) {
            $cents[(int) $row->group_company_id][(string) $row->bucket] = ExposureSupport::cents($row->total);
        }

        return $cents;
    }

    /**
     * Names of the companies, one query.
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    public static function companyNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('companies')->whereIn('id', $ids)->pluck('denomination', 'id')
            ->mapWithKeys(fn (mixed $name, mixed $id): array => [(int) $id => (string) ($name ?? $id)])
            ->all();
    }
}
