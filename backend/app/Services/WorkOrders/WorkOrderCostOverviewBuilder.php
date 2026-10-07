<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\QuoteLineType;
use App\Http\Resources\QuoteLineResource;
use App\Http\Resources\WorkOrderCostResource;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\Models\WorkOrderCost;
use Illuminate\Support\Collection;

/**
 * Builds the WorkOrderCostOverview payload of spec 0190: the actual cost
 * lines, the offer's budget (COST lines allocated to the commessa's REVENUE
 * lines, plus the informational generic ones) and the net-amount comparison
 * (D-5: delta = actual - budget, margin = revenue - cost). Unallocated budget
 * lines never enter the totals (D-1); unattributed actual costs always do.
 * A line's revenue is its effective revenue (spec 0202, D-8): the supplier
 * commission on a RECEIVED line, the net amount otherwise.
 * Amounts are summed as integer cents to avoid float drift.
 */
final class WorkOrderCostOverviewBuilder
{
    /** Relations the QuoteLineResource reads per budget line. */
    private const array BUDGET_LINE_RELATIONS = ['product.category', 'product.productTypology', 'product.unitOfMeasure', 'vatRate', 'unitOfMeasure', 'quote', 'commissions'];

    public function __construct(private readonly LineEffectiveRevenue $effectiveRevenue) {}

    /**
     * @return array<string, mixed>
     */
    public function build(WorkOrder $workOrder): array
    {
        // Step 1: load the actual costs, the commessa's revenue lines and the offer's COST lines
        $costs = $workOrder->costs()->with(['product.category', 'product.unitOfMeasure', 'vatRate', 'unitOfMeasure', 'supplier'])->get();
        $revenueLines = $workOrder->quoteLines()->with(['product', 'commissions'])->get();
        $budgetLines = $this->budgetLines($workOrder);
        $allocated = $budgetLines->whereIn('offer_line_id', $revenueLines->modelKeys())->values();
        $unallocated = $budgetLines->whereNull('offer_line_id')->values();

        // Step 2: compare per revenue line
        $rows = $revenueLines->map(fn (QuoteLine $line): array => $this->row($line, $allocated, $costs))->all();
        $unattributed = $this->cents($costs->whereNull('quote_line_id'), 'net_amount');

        // Step 3: totals (actual includes unattributed; budget excludes unallocated)
        $revenue = array_sum(array_column($rows, 'revenue_cents'));
        $budget = array_sum(array_column($rows, 'budget_cents'));
        $actual = array_sum(array_column($rows, 'actual_cents')) + $unattributed;

        return [
            'lines' => WorkOrderCostResource::collection($costs),
            'budget' => [
                'allocated_lines' => QuoteLineResource::collection($allocated),
                'unallocated_lines' => QuoteLineResource::collection($unallocated),
            ],
            'comparison' => [
                'rows' => array_map($this->publicRow(...), $rows),
                'unattributed_actual_cost_net' => $this->format($unattributed),
                'totals' => [
                    'revenue_net' => $this->format($revenue),
                    'budget_cost_net' => $this->format($budget),
                    'actual_cost_net' => $this->format($actual),
                    'delta_net' => $this->format($actual - $budget),
                    'budget_margin_net' => $this->format($revenue - $budget),
                    'actual_margin_net' => $this->format($revenue - $actual),
                ],
            ],
        ];
    }

    /**
     * The offer's COST lines that are either allocated to some REVENUE line
     * (filtered to this commessa's own by build()) or generic.
     *
     * @return Collection<int, QuoteLine>
     */
    private function budgetLines(WorkOrder $workOrder): Collection
    {
        return QuoteLine::query()
            ->where('quote_id', $workOrder->quote_id)
            ->where('line_type', QuoteLineType::Cost)
            ->with(self::BUDGET_LINE_RELATIONS)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, QuoteLine>  $allocated
     * @param  Collection<int, WorkOrderCost>  $costs
     * @return array<string, mixed>
     */
    private function row(QuoteLine $line, Collection $allocated, Collection $costs): array
    {
        return [
            'quote_line_id' => $line->id,
            'product' => ['id' => $line->product?->id, 'code' => $line->product?->code, 'name' => $line->product?->name],
            'revenue_cents' => $this->effectiveRevenue->cents($line),
            'budget_cents' => $this->cents($allocated->where('offer_line_id', $line->id), 'net_amount'),
            'actual_cents' => $this->cents($costs->where('quote_line_id', $line->id), 'net_amount'),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function publicRow(array $row): array
    {
        return [
            'quote_line_id' => $row['quote_line_id'],
            'product' => $row['product'],
            'revenue_net' => $this->format($row['revenue_cents']),
            'budget_cost_net' => $this->format($row['budget_cents']),
            'actual_cost_net' => $this->format($row['actual_cents']),
            'delta_net' => $this->format($row['actual_cents'] - $row['budget_cents']),
        ];
    }

    /**
     * @param  Collection<int, QuoteLine|WorkOrderCost>  $models
     */
    private function cents(Collection $models, string $column): int
    {
        return (int) $models->sum(fn ($model): int => (int) round((float) $model->{$column} * 100));
    }

    private function format(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
