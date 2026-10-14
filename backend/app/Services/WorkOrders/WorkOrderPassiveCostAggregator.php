<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\CostType;
use App\Models\WorkOrder;
use App\Services\PurchaseInvoices\PurchaseInvoiceDraftBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The commessa's actual costs by cost type and source (spec 0252, ADR 0018):
 * manual costs, committed purchase order lines (invoiceable orders, quantity
 * not yet billed by a payable document, spec 0254 D-9) and payable document
 * lines. Billed quantity leaves the committed: the document line replaces it. Three
 * aggregate queries plus one for the cost types, whatever the number of rows.
 * Amounts are net, summed as integer cents.
 */
final class WorkOrderPassiveCostAggregator
{
    /**
     * Committed net of one order line: the billable ceiling (received once the
     * residual is closed, ordered otherwise) minus what documents bill, never
     * below zero, times the unit price, per line to the cent. CASE WHEN instead
     * of GREATEST so it runs on MySQL and SQLite; constant text, no input.
     */
    private const string COMMITTED_AMOUNT = 'ROUND(CASE WHEN (CASE WHEN purchase_order_lines.residual_closed_at IS NOT NULL THEN purchase_order_lines.received_quantity ELSE purchase_order_lines.quantity END) - COALESCE(billed.billed_quantity, 0) > 0 THEN (CASE WHEN purchase_order_lines.residual_closed_at IS NOT NULL THEN purchase_order_lines.received_quantity ELSE purchase_order_lines.quantity END) - COALESCE(billed.billed_quantity, 0) ELSE 0 END * purchase_order_lines.unit_price, 2)';

    /**
     * Ordered by cost type sort order, the row without a type last.
     *
     * @return Collection<int, array{cost_type: array<string, mixed>|null, manual_cents: int, committed_cents: int, invoiced_cents: int}>
     */
    public function byCostType(WorkOrder $workOrder): Collection
    {
        // Step 1: the three sources, summed per cost type id (0 = no type)
        $sources = [
            'manual_cents' => $this->sums(DB::table('work_order_costs')->where('work_order_id', $workOrder->id), 'net_amount', 'cost_type_id'),
            'committed_cents' => $this->sums($this->committedQuery($workOrder), self::COMMITTED_AMOUNT, 'purchase_order_lines.cost_type_id'),
            'invoiced_cents' => $this->sums(DB::table('purchase_invoice_lines')->where('work_order_id', $workOrder->id), 'net_amount', 'cost_type_id'),
        ];

        // Step 2: the types involved, in display order
        $ids = collect($sources)->flatMap(fn (array $cents): array => array_keys($cents))->unique()->values();
        $types = CostType::query()->whereIn('id', $ids->all())->orderBy('sort_order')->orderBy('name')->orderBy('id')->get();

        // Step 3: one row per type, the untyped one last
        $rows = $types->map(fn (CostType $type): array => $this->row($type->id, [
            'id' => $type->id, 'name' => $type->name, 'color' => $type->color, 'nature' => $type->nature?->value,
        ], $sources));

        if ($ids->contains(0)) {
            $rows->push($this->row(0, null, $sources));
        }

        return $rows->values();
    }

    /**
     * Order lines of the commessa on invoiceable orders, with the quantity
     * billed on them by any payable document (spec 0254, D-2, D-9).
     */
    private function committedQuery(WorkOrder $workOrder): Builder
    {
        $statuses = array_map(fn (PurchaseOrderStatus $status): string => $status->value, PurchaseInvoiceDraftBuilder::INVOICEABLE_STATUSES);
        $billed = DB::table('purchase_invoice_lines')
            ->whereNotNull('purchase_order_line_id')
            ->groupBy('purchase_order_line_id')
            ->selectRaw('purchase_order_line_id, SUM(quantity) as billed_quantity');

        return DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoinSub($billed, 'billed', 'billed.purchase_order_line_id', '=', 'purchase_order_lines.id')
            ->where('purchase_order_lines.work_order_id', $workOrder->id)
            ->whereIn('purchase_orders.status', $statuses);
    }

    /**
     * @return array<int, int> cents keyed by cost type id (0 = no type)
     */
    private function sums(Builder $query, string $amountColumn, string $typeColumn): array
    {
        return $query->groupBy($typeColumn)
            ->selectRaw("{$typeColumn} as cost_type_id, SUM({$amountColumn}) as total")
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->cost_type_id => (int) round((float) $row->total * 100)])
            ->all();
    }

    /**
     * @param  array<string, mixed>|null  $type
     * @param  array<string, array<int, int>>  $sources
     * @return array{cost_type: array<string, mixed>|null, manual_cents: int, committed_cents: int, invoiced_cents: int}
     */
    private function row(int $typeId, ?array $type, array $sources): array
    {
        return [
            'cost_type' => $type,
            'manual_cents' => $sources['manual_cents'][$typeId] ?? 0,
            'committed_cents' => $sources['committed_cents'][$typeId] ?? 0,
            'invoiced_cents' => $sources['invoiced_cents'][$typeId] ?? 0,
        ];
    }
}
