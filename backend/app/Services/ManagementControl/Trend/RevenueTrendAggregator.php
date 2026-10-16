<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Trend;

use App\DataObjects\ManagementControl\MonthlyTrendFilters;
use App\Enums\InvoiceType;
use App\Services\ManagementControl\Exposure\ExposureSupport;
use App\Services\ManagementControl\FlowSupport;
use App\Services\ManagementControl\WorkOrderFunctionShares;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Support\Facades\DB;

/**
 * The revenue of the trend (spec 0270, D-6/D-7): the net of the lines of the
 * invoices (not the proforma) by month of the document date. A line tied to an
 * offer line goes to the function of its product category; a line without it
 * follows the invoice's commessa, split by the commessa's function weights;
 * anything else is "unattributed". Sites come from the invoice's offer.
 */
final class RevenueTrendAggregator
{
    private const array SCOPE_COLUMNS = [
        'company' => 'invoices.company_id',
        'company_site' => 'quotes.company_site_id',
        'operational_site' => 'quotes.operational_site_id',
    ];

    public function __construct(private readonly CategoryHierarchy $categories) {}

    /**
     * One aggregate query over the interval and its previous-year counterpart.
     *
     * @return array<int, array{month: string, category_id: int|null, work_order_id: int|null, cents: int}>
     */
    public function rows(MonthlyTrendFilters $filters): array
    {
        [$from, $until] = $filters->queryRange();
        $query = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->leftJoin('quotes', 'quotes.id', '=', 'invoices.quote_id')
            ->leftJoin('quote_lines', 'quote_lines.id', '=', 'invoice_lines.quote_line_id')
            ->leftJoin('products', 'products.id', '=', 'quote_lines.product_id')
            ->where('invoices.type', InvoiceType::Invoice->value)
            ->where('invoices.document_date', '>=', $from)
            ->where('invoices.document_date', '<', $until);

        return FlowSupport::scope($query, $filters->companyIds, $filters->companySiteIds, $filters->operationalSiteIds, self::SCOPE_COLUMNS)
            ->selectRaw(
                'SUBSTR(invoices.document_date, 1, 7) AS doc_month, products.category_id AS attr_category_id, '
                .'CASE WHEN invoice_lines.quote_line_id IS NULL THEN invoices.work_order_id END AS attr_work_order_id, '
                .'SUM(invoice_lines.net_amount) AS total',
            )
            ->groupBy('doc_month', 'attr_category_id', 'attr_work_order_id')
            ->get()
            ->map(fn (object $row): array => [
                'month' => (string) $row->doc_month,
                'category_id' => $row->attr_category_id === null ? null : (int) $row->attr_category_id,
                'work_order_id' => $row->attr_work_order_id === null ? null : (int) $row->attr_work_order_id,
                'cents' => ExposureSupport::cents($row->total),
            ])->all();
    }

    /**
     * Revenue cents by function key by month, for the given months only.
     *
     * @param  array<int, array{month: string, category_id: int|null, work_order_id: int|null, cents: int}>  $rows
     * @param  array<int, string>  $months
     * @param  array<int, array<int, int>>  $weights  function weights by work order id
     * @return array<string, array<string, int>>
     */
    public function byFunction(array $rows, array $months, array $weights): array
    {
        $categoryFunctions = $this->categories->effectiveBusinessFunctionSummaries();
        $byFunction = [];

        foreach ($rows as $row) {
            if (! in_array($row['month'], $months, true)) {
                continue;
            }

            $shares = $row['work_order_id'] === null
                ? [$row['category_id'] === null ? 0 : ($categoryFunctions[$row['category_id']]['id'] ?? 0) => $row['cents']]
                : $this->shares($row['cents'], $weights[$row['work_order_id']] ?? []);

            foreach ($shares as $functionId => $cents) {
                $key = $functionId === 0 ? FlowSupport::UNATTRIBUTED : (string) $functionId;
                $byFunction[$key][$row['month']] = ($byFunction[$key][$row['month']] ?? 0) + $cents;
            }
        }

        return $byFunction;
    }

    /**
     * @param  array<int, int>  $weights
     * @return array<int, int> cents by function id (0 = none)
     */
    private function shares(int $cents, array $weights): array
    {
        $split = WorkOrderFunctionShares::split(['cents' => $cents], $weights);

        return $split === [] ? [0 => $cents] : array_map(fn (array $share): int => $share['cents'], $split);
    }
}
