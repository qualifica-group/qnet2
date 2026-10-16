<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\PurchaseOrderStatus;
use App\Services\PurchaseInvoices\PurchaseInvoiceDraftBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The committed (ordered, not yet billed) cost of purchase order lines, shared
 * by the commessa actuals (spec 0252) and the management control builders
 * (spec 0268, D-14): the formula and the base query live here once. A line is
 * committed only on an invoiceable order, net of the quantity any payable
 * document already bills on it (spec 0254, D-2, D-9). Amounts are net, summed
 * as integer cents.
 */
final class CommittedOrderLineQuery
{
    /**
     * Committed net of one order line: the billable ceiling (received once the
     * residual is closed, ordered otherwise) minus what documents bill, never
     * below zero, times the unit price, per line to the cent. CASE WHEN instead
     * of GREATEST so it runs on MySQL and SQLite; constant text, no input.
     */
    public const string COMMITTED_AMOUNT = 'ROUND(CASE WHEN (CASE WHEN purchase_order_lines.residual_closed_at IS NOT NULL THEN purchase_order_lines.received_quantity ELSE purchase_order_lines.quantity END) - COALESCE(billed.billed_quantity, 0) > 0 THEN (CASE WHEN purchase_order_lines.residual_closed_at IS NOT NULL THEN purchase_order_lines.received_quantity ELSE purchase_order_lines.quantity END) - COALESCE(billed.billed_quantity, 0) ELSE 0 END * purchase_order_lines.unit_price, 2)';

    /**
     * Order lines of invoiceable orders (joined to `purchase_orders`), with the
     * billed quantity as `billed.billed_quantity`.
     */
    public function lines(): Builder
    {
        $statuses = array_map(fn (PurchaseOrderStatus $status): string => $status->value, PurchaseInvoiceDraftBuilder::INVOICEABLE_STATUSES);
        $billed = DB::table('purchase_invoice_lines')
            ->whereNotNull('purchase_order_line_id')
            ->groupBy('purchase_order_line_id')
            ->selectRaw('purchase_order_line_id, SUM(quantity) as billed_quantity');

        return DB::table('purchase_order_lines')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->leftJoinSub($billed, 'billed', 'billed.purchase_order_line_id', '=', 'purchase_order_lines.id')
            ->whereIn('purchase_orders.status', $statuses);
    }

    /**
     * @param  array<int, int>  $workOrderIds
     */
    public function forWorkOrders(array $workOrderIds): Builder
    {
        return $this->lines()->whereIn('purchase_order_lines.work_order_id', $workOrderIds);
    }

    public function withoutWorkOrder(): Builder
    {
        return $this->lines()->whereNull('purchase_order_lines.work_order_id');
    }

    /**
     * Committed cents of the query's lines, one entry per value of `$groupColumn`.
     *
     * @return array<int, int> cents keyed by the group value (0 = null)
     */
    public function committedCentsBy(Builder $query, string $groupColumn): array
    {
        return $this->centsBy($query, self::COMMITTED_AMOUNT, $groupColumn);
    }

    /**
     * The groups committing the most, largest first (ties by group value), with
     * the number of orders that still commit something. `$groupColumn` is an
     * internal constant, never request input.
     *
     * @return array<int, array{id: int, cents: int, orders: int}>
     */
    public function topCommittedBy(Builder $query, string $groupColumn, int $limit): array
    {
        $amount = self::COMMITTED_AMOUNT;

        return $query->groupBy($groupColumn)
            ->selectRaw("{$groupColumn} as group_id, SUM({$amount}) as total, COUNT(DISTINCT CASE WHEN {$amount} > 0 THEN purchase_orders.id END) as orders")
            ->having('total', '>', 0)
            ->orderByDesc('total')->orderBy('group_id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => ['id' => (int) $row->group_id, 'cents' => (int) round((float) $row->total * 100), 'orders' => (int) $row->orders])
            ->all();
    }

    /**
     * Grouped sum in integer cents. `$amountExpression` and `$groupColumn` are
     * internal constants, never request input.
     *
     * @return array<int, int> cents keyed by the group value (0 = null)
     */
    public function centsBy(Builder $query, string $amountExpression, string $groupColumn): array
    {
        return $query->groupBy($groupColumn)
            ->selectRaw("{$groupColumn} as group_id, SUM({$amountExpression}) as total")
            ->get()
            ->mapWithKeys(fn (object $row): array => [(int) $row->group_id => (int) round((float) $row->total * 100)])
            ->all();
    }
}
