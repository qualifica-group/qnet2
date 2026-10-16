<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\DataObjects\ManagementControl\IncomeStatementFilters;
use App\Enums\QuoteLineType;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The income statement (spec 0268, D-22): the totals of the selected commesse,
 * the statement by business function and, optionally, the same statement for
 * each segment of a dimension. The figures of each commessa stay internal, they
 * only feed the split. Read-only, computed with a constant number of queries
 * (D-16); amounts are summed as integer cents and leave as numbers with two
 * decimals. Visibility is not scoped (D-7).
 */
final class IncomeStatementBuilder
{
    public function __construct(
        private readonly WorkOrderFigureSources $sources,
        private readonly IncomeStatementGrouper $grouper,
        private readonly IncomeStatementSegmenter $segmenter,
        private readonly CategoryHierarchy $categories,
    ) {}

    /**
     * @return array{totals: array<string, mixed>, by_business_function: array<int, array<string, mixed>>, segments: array<int, array<string, mixed>>}
     */
    public function handle(IncomeStatementFilters $filters): array
    {
        // Step 1: the commesse selected by the filters
        $workOrders = $this->workOrders($filters);

        // Step 2: their base figures, one aggregate query per source
        $collected = $this->sources->collect($workOrders->pluck('id')->map(fn (mixed $id): int => (int) $id)->all());

        // Step 3: the statement by function, and the segments with their own
        $functions = $this->grouper->functions($workOrders, $collected['figures'], $collected['weights']);
        $segments = $this->segmenter->segments($workOrders, $collected['figures'], $collected['weights'], $filters->segmentBy);

        // Step 4: function names, loaded once for every list
        $names = $this->grouper->functionNames([$functions, ...array_column($segments, 'functions')]);
        $basis = $filters->revenueBasis;

        return [
            'totals' => ['work_orders_count' => $workOrders->count(), ...$this->figures($this->sum(array_values($collected['figures'])), $basis)],
            'by_business_function' => $this->functionRows($functions, $names, $basis),
            'segments' => array_map(fn (array $segment): array => [
                'key' => $segment['key'],
                'label' => $segment['label'],
                'work_orders_count' => $segment['work_orders_count'],
                ...$this->figures($segment['base'], $basis),
                'by_business_function' => $this->functionRows($segment['functions'], $names, $basis),
            ], $segments),
        ];
    }

    /**
     * @return Collection<int, object>
     */
    private function workOrders(IncomeStatementFilters $filters): Collection
    {
        $query = WorkOrderReferenceQuote::join(DB::table('work_orders'))
            ->leftJoin('companies', 'companies.id', '=', 'quotes.company_id')
            ->leftJoin('company_sites', 'company_sites.id', '=', 'quotes.company_site_id')
            ->leftJoin('operational_sites', 'operational_sites.id', '=', 'quotes.operational_site_id')
            ->leftJoin('referents', 'referents.id', '=', 'quotes.commercial_id')
            ->select([
                'work_orders.id', 'work_orders.type',
                'quotes.company_id', 'companies.denomination as company_name',
                'quotes.company_site_id', 'company_sites.name as company_site_name',
                'quotes.operational_site_id', 'operational_sites.alias as operational_site_alias',
                'quotes.commercial_id', 'referents.name as commercial_name',
            ]);

        return $this->filtered($query, $filters)->get();
    }

    private function filtered(Builder $query, IncomeStatementFilters $filters): Builder
    {
        return $query
            ->when($filters->dateFrom !== null, fn (Builder $q) => $q->where('work_orders.start_date', '>=', $filters->dateFrom))
            ->when($filters->dateTo !== null, fn (Builder $q) => $q->where('work_orders.start_date', '<=', $filters->dateTo))
            ->when($filters->workOrderTypes !== [], fn (Builder $q) => $q->whereIn('work_orders.type', $filters->workOrderTypes))
            ->when($filters->companyIds !== [], fn (Builder $q) => $q->whereIn('quotes.company_id', $filters->companyIds))
            ->when($filters->companySiteIds !== [], fn (Builder $q) => $q->whereIn('quotes.company_site_id', $filters->companySiteIds))
            ->when($filters->operationalSiteIds !== [], fn (Builder $q) => $q->whereIn('quotes.operational_site_id', $filters->operationalSiteIds))
            ->when($filters->commercialIds !== [], fn (Builder $q) => $q->whereIn('quotes.commercial_id', $filters->commercialIds))
            ->when($filters->businessFunctionIds !== [], fn (Builder $q) => $q->whereExists(
                DB::table('quote_line_work_order')
                    ->join('quote_lines', 'quote_lines.id', '=', 'quote_line_work_order.quote_line_id')
                    ->join('products', 'products.id', '=', 'quote_lines.product_id')
                    ->whereColumn('quote_line_work_order.work_order_id', 'work_orders.id')
                    ->where('quote_lines.line_type', QuoteLineType::Revenue->value)
                    ->whereIn('products.category_id', $this->categoriesOfFunctions($filters->businessFunctionIds))
                    ->select(DB::raw(1))
            ));
    }

    /**
     * Ids of the categories whose EFFECTIVE business function (own or inherited,
     * spec 0040) is one of the given ones.
     *
     * @param  array<int, int>  $functionIds
     * @return array<int, int>
     */
    private function categoriesOfFunctions(array $functionIds): array
    {
        return array_keys(array_filter(
            $this->categories->effectiveBusinessFunctionSummaries(),
            fn (?array $function): bool => $function !== null && in_array($function['id'], $functionIds, true),
        ));
    }

    /**
     * @param  array<int, array{key: string, work_orders_count: int, base: array<string, int>}>  $functions
     * @param  array<string, string>  $names
     * @return array<int, array<string, mixed>>
     */
    private function functionRows(array $functions, array $names, string $basis): array
    {
        $rows = array_map(fn (array $function): array => [
            'key' => $function['key'],
            'label' => $function['key'] === IncomeStatementGrouper::UNATTRIBUTED ? null : ($names[$function['key']] ?? $function['key']),
            'work_orders_count' => $function['work_orders_count'],
            ...$this->figures($function['base'], $basis),
        ], $functions);

        return $this->grouper->sorted($rows);
    }

    /**
     * @param  array<int, array<string, int>>  $rows
     * @return array<string, int>
     */
    private function sum(array $rows): array
    {
        $total = array_fill_keys(WorkOrderFigureSources::BASE_FIGURES, 0);
        foreach ($rows as $row) {
            foreach ($row as $figure => $cents) {
                $total[$figure] += $cents;
            }
        }

        return $total;
    }

    /**
     * The wire figures: base and derived amounts (D-6, D-13), in euros.
     *
     * @param  array<string, int>  $base
     * @return array<string, float|int>
     */
    private function figures(array $base, string $revenueBasis): array
    {
        $revenueActual = $base['revenue_'.$revenueBasis];
        $costActual = $base['cost_manual'] + $base['cost_committed'] + $base['cost_invoiced'] + $base['cost_labor'];
        $marginBudget = $base['revenue_contract'] - $base['cost_budget'] - $base['commission_budget'];
        $marginActual = $revenueActual - $costActual - $base['commission_actual'];

        $minutes = array_intersect_key($base, array_flip(WorkOrderFigureSources::MINUTE_FIGURES));

        return $minutes + array_map($this->euros(...), [
            ...array_diff_key($base, $minutes),
            'revenue_actual' => $revenueActual,
            'cost_actual' => $costActual,
            'margin_budget' => $marginBudget,
            'margin_actual' => $marginActual,
            'cost_delta' => $costActual - $base['cost_budget'],
            'margin_delta' => $marginActual - $marginBudget,
        ]);
    }

    private function euros(int $cents): float
    {
        return round($cents / 100, 2);
    }
}
