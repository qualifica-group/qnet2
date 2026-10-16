<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Trend;

use App\DataObjects\ManagementControl\MonthlyTrendFilters;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use App\Services\ManagementControl\WorkOrderFunctionShares;
use Illuminate\Support\Facades\DB;

/**
 * The costs of the trend (spec 0270, D-6/D-7): the net of the lines of the
 * payable documents (proforma and invoices) by month of the document date. A
 * line tied to a commessa is split by the commessa's function weights, the
 * others are structure costs. The same rows also feed the split by nature.
 */
final class CostTrendAggregator
{
    /** Key of the costs tied to no commessa. */
    public const string STRUCTURE = 'structure';

    private const array SCOPE_COLUMNS = [
        'company' => 'purchase_invoices.company_id',
        'company_site' => 'purchase_invoices.company_site_id',
        'operational_site' => 'purchase_invoices.operational_site_id',
    ];

    /**
     * One aggregate query over the interval and its previous-year counterpart.
     *
     * @return array<int, array{month: string, work_order_id: int|null, nature: string|null, cents: int}>
     */
    public function rows(MonthlyTrendFilters $filters): array
    {
        [$from, $until] = $filters->queryRange();
        $query = DB::table('purchase_invoice_lines')
            ->join('purchase_invoices', 'purchase_invoices.id', '=', 'purchase_invoice_lines.purchase_invoice_id')
            ->leftJoin('cost_types', 'cost_types.id', '=', 'purchase_invoice_lines.cost_type_id')
            ->where('purchase_invoices.document_date', '>=', $from)
            ->where('purchase_invoices.document_date', '<', $until);

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)
            ->selectRaw(
                'SUBSTR(purchase_invoices.document_date, 1, 7) AS doc_month, purchase_invoice_lines.work_order_id AS attr_work_order_id, '
                .'cost_types.nature AS attr_nature, SUM(purchase_invoice_lines.net_amount) AS total',
            )
            ->groupBy('doc_month', 'attr_work_order_id', 'attr_nature')
            ->get()
            ->map(fn (object $row): array => [
                'month' => (string) $row->doc_month,
                'work_order_id' => $row->attr_work_order_id === null ? null : (int) $row->attr_work_order_id,
                'nature' => $row->attr_nature === null ? null : (string) $row->attr_nature,
                'cents' => ExposureSupport::cents($row->total),
            ])->all();
    }

    /**
     * Cost cents by function key by month, for the given months only.
     *
     * @param  array<int, array{month: string, work_order_id: int|null, nature: string|null, cents: int}>  $rows
     * @param  array<int, string>  $months
     * @param  array<int, array<int, int>>  $weights  function weights by work order id
     * @return array<string, array<string, int>>
     */
    public function byFunction(array $rows, array $months, array $weights): array
    {
        $byFunction = [];

        foreach ($rows as $row) {
            if (! in_array($row['month'], $months, true)) {
                continue;
            }

            $shares = $row['work_order_id'] === null
                ? [self::STRUCTURE => $row['cents']]
                : $this->shares($row['cents'], $weights[$row['work_order_id']] ?? []);

            foreach ($shares as $key => $cents) {
                $byFunction[$key][$row['month']] = ($byFunction[$key][$row['month']] ?? 0) + $cents;
            }
        }

        return $byFunction;
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<string, int> cents by function key
     */
    private function shares(int $cents, array $weights): array
    {
        $split = WorkOrderFunctionShares::split(['cents' => $cents], $weights);
        if ($split === []) {
            return [FlowSupport::UNATTRIBUTED => $cents];
        }

        $shares = [];
        foreach ($split as $functionId => $share) {
            $shares[$functionId === 0 ? FlowSupport::UNATTRIBUTED : (string) $functionId] = $share['cents'];
        }

        return $shares;
    }
}
