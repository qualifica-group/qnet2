<?php

declare(strict_types=1);

namespace App\Services\ManagementControl;

use App\Enums\CommissionRecipientRole;
use App\Enums\InvoiceType;
use App\Enums\QuoteLineType;
use App\Enums\SupplierCommissionDirection;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Services\ProductCategories\CategoryHierarchy;
use App\Services\Purchasing\CommittedOrderLineQuery;
use App\Services\WorkOrders\LineEffectiveRevenue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The base figures of a set of commesse (spec 0268, D-14/D-16): one aggregate
 * query per source, grouped by work order, so the number of queries does not
 * depend on how many commesse are asked for. Every amount is integer cents.
 *
 * Returned `figures` hold the base figures (the labor ones from LaborCostSource, spec 0272) per work order id; `weights`
 * hold, per work order id, the effective revenue of its REVENUE lines by
 * business function id (0 = no function), the basis of the D-11 split.
 */
final class WorkOrderFigureSources
{
    public const array BASE_FIGURES = [
        'revenue_contract', 'revenue_invoiced', 'revenue_collected',
        'cost_budget', 'cost_manual', 'cost_committed', 'cost_invoiced',
        'commission_budget', 'commission_actual',
        'cost_labor', 'labor_minutes', 'unpriced_minutes',
    ];

    /** Figures that are minutes, not cents. */
    public const array MINUTE_FIGURES = ['labor_minutes', 'unpriced_minutes'];

    /** Recipient roles whose line commission is always a cost of the commessa (D-8). */
    private const array COST_ROLES = [CommissionRecipientRole::Commercial, CommissionRecipientRole::Reporter, CommissionRecipientRole::Supervisor];

    public function __construct(
        private readonly CommittedOrderLineQuery $committed,
        private readonly LineEffectiveRevenue $effectiveRevenue,
        private readonly CategoryHierarchy $categories,
        private readonly WorkOrderFunctionShares $functionShares,
        private readonly LaborCostSource $labor,
    ) {}

    /**
     * @param  array<int, int>  $workOrderIds
     * @return array{figures: array<int, array<string, int>>, weights: array<int, array<int, int>>}
     */
    public function collect(array $workOrderIds): array
    {
        // Step 1: the REVENUE lines assigned to each commessa
        $pairs = DB::table('quote_line_work_order')->whereIn('work_order_id', $workOrderIds)->get(['work_order_id', 'quote_line_id']);
        $lines = $this->revenueLines($pairs->pluck('quote_line_id')->unique()->values()->all());
        $pairsByOrder = $pairs->groupBy('work_order_id');

        // Step 2: per-source sums by work order
        $invoiced = $this->committed->centsBy(
            DB::table('invoices')->where('type', InvoiceType::Invoice->value)->whereIn('work_order_id', $workOrderIds),
            'net_amount',
            'work_order_id',
        );
        $manual = $this->committed->centsBy(DB::table('work_order_costs')->whereIn('work_order_id', $workOrderIds), 'net_amount', 'work_order_id');
        $committed = $this->committed->committedCentsBy($this->committed->forWorkOrders($workOrderIds), 'purchase_order_lines.work_order_id');
        $payable = $this->committed->centsBy(DB::table('purchase_invoice_lines')->whereIn('work_order_id', $workOrderIds), 'net_amount', 'work_order_id');
        $collected = $this->collectedByWorkOrder($workOrderIds);
        $labor = $this->labor->byWorkOrder($workOrderIds);
        $budgetByLine = $this->budgetByRevenueLine($lines->keys()->all());
        $accrualByLine = $this->committed->centsBy(DB::table('commission_accruals')->whereIn('quote_line_id', $lines->keys()->all()), 'amount', 'quote_line_id');

        // Step 3: assemble the base figures and the function weights per commessa
        $categoryFunctions = $this->categories->effectiveBusinessFunctionSummaries();
        $figures = [];
        $weights = [];
        foreach ($workOrderIds as $id) {
            $ownLines = ($pairsByOrder->get($id) ?? collect())->map(fn (object $pair): ?QuoteLine => $lines->get($pair->quote_line_id))->filter();
            $figures[$id] = [
                'revenue_contract' => (int) $ownLines->sum(fn (QuoteLine $line): int => $this->effectiveRevenue->cents($line)),
                'revenue_invoiced' => $invoiced[$id] ?? 0,
                'revenue_collected' => $collected[$id] ?? 0,
                'cost_budget' => (int) $ownLines->sum(fn (QuoteLine $line): int => $budgetByLine[$line->id] ?? 0),
                'cost_manual' => $manual[$id] ?? 0,
                'cost_committed' => $committed[$id] ?? 0,
                'cost_invoiced' => $payable[$id] ?? 0,
                'commission_budget' => (int) $ownLines->sum(fn (QuoteLine $line): int => $this->budgetCommissionCents($line)),
                'commission_actual' => (int) $ownLines->sum(fn (QuoteLine $line): int => $accrualByLine[$line->id] ?? 0),
                'cost_labor' => $labor[$id]['cents'] ?? 0,
                'labor_minutes' => $labor[$id]['minutes'] ?? 0,
                'unpriced_minutes' => $labor[$id]['unpriced_minutes'] ?? 0,
            ];
            $weights[$id] = $this->functionShares->fromLines($ownLines, $categoryFunctions);
        }

        return ['figures' => $figures, 'weights' => $weights];
    }

    /**
     * @param  array<int, int>  $lineIds
     * @return Collection<int, QuoteLine> keyed by id
     */
    private function revenueLines(array $lineIds): Collection
    {
        return QuoteLine::query()
            ->whereIn('id', $lineIds)
            ->where('line_type', QuoteLineType::Revenue)
            ->with(['commissions', 'product'])
            ->get()
            ->keyBy('id');
    }

    /**
     * Net of the COST lines allocated to each REVENUE line (spec 0190, D-1).
     *
     * @param  array<int, int>  $revenueLineIds
     * @return array<int, int>
     */
    private function budgetByRevenueLine(array $revenueLineIds): array
    {
        return $this->committed->centsBy(
            DB::table('quote_lines')->where('line_type', QuoteLineType::Cost->value)->whereIn('offer_line_id', $revenueLineIds),
            'net_amount',
            'offer_line_id',
        );
    }

    /**
     * Net collected of the active invoices of each commessa: the collected
     * amount of every invoice scaled to its net, rounded to the cent per
     * invoice (D-13). A zero total document collects nothing.
     *
     * @param  array<int, int>  $workOrderIds
     * @return array<int, int>
     */
    private function collectedByWorkOrder(array $workOrderIds): array
    {
        $collected = [];
        $rows = DB::table('invoices')
            ->join('invoice_installments', 'invoice_installments.invoice_id', '=', 'invoices.id')
            ->where('invoices.type', InvoiceType::Invoice->value)
            ->whereIn('invoices.work_order_id', $workOrderIds)
            ->groupBy('invoices.id', 'invoices.work_order_id', 'invoices.net_amount', 'invoices.total_amount')
            ->selectRaw('invoices.work_order_id, invoices.net_amount, invoices.total_amount, SUM(invoice_installments.collected_amount) as collected')
            ->get();

        foreach ($rows as $row) {
            $total = $this->cents($row->total_amount);
            $share = $total === 0 ? 0 : (int) round($this->cents($row->collected) * $this->cents($row->net_amount) / $total);
            $collected[(int) $row->work_order_id] = ($collected[(int) $row->work_order_id] ?? 0) + $share;
        }

        return $collected;
    }

    /** Line commissions that reduce the margin: the supplier one only when it is paid out (D-8). */
    private function budgetCommissionCents(QuoteLine $line): int
    {
        return (int) $line->commissions
            ->filter(fn (QuoteLineCommission $commission): bool => in_array($commission->recipient_role, self::COST_ROLES, true)
                || ($commission->recipient_role === CommissionRecipientRole::Supplier
                    && $line->supplier_commission_direction === SupplierCommissionDirection::Paid))
            ->sum(fn (QuoteLineCommission $commission): int => $this->cents($commission->calculated_amount));
    }

    private function cents(mixed $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
