<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * An invoice billing purchase order lines receives what it bills and is not
 * received yet (spec 0254, D-12): one receipt per order that needs it, linked
 * to the document. It is an effect of the payable-document permission, so no
 * `purchase-orders.receive` check. Must run inside the caller's transaction,
 * after the document is saved with type invoice.
 *
 * Lock order: orders (id order), then per order its lines and the document
 * lines referencing them, then request lines (synchronizer).
 */
final class PurchaseOrderInvoiceReceiptService
{
    public function __construct(
        private readonly PurchaseOrderReceiptService $receipts,
        private readonly PurchaseOrderFulfillmentSynchronizer $synchronizer,
    ) {}

    /**
     * @param  bool  $errorOnLine  422 key: the first document row of the order (`lines.N.purchase_order_line_id`) or `document_number`
     * @param  bool  $requireReceivable  false only for the legacy import (spec 0298, D-2): an invoiced draft order is received too
     *
     * @throws ValidationException when an order that needs a receipt is not receivable
     */
    public function receive(PurchaseInvoice $document, User $actor, bool $errorOnLine, bool $requireReceivable = true): void
    {
        // Step 1: the order lines the document bills, grouped by order
        $rows = $document->lines()->orderBy('id')->get(['id', 'purchase_order_line_id']);
        $orderOf = PurchaseOrderLine::query()
            ->whereIn('id', $rows->pluck('purchase_order_line_id')->filter()->all())
            ->pluck('purchase_order_id', 'id');

        if ($orderOf->isEmpty()) {
            return;
        }

        // Step 2: lock the orders in id order, then receive order by order
        $orders = PurchaseOrder::query()
            ->whereIn('id', $orderOf->unique()->all())->orderBy('id')->lockForUpdate()
            ->get()->keyBy('id');

        foreach ($orders as $order) {
            $billed = $rows->filter(fn ($row): bool => $orderOf->get($row->purchase_order_line_id) === $order->id);
            $this->receiveOrder($order, $document, $billed, $actor, $errorOnLine, $requireReceivable);
        }
    }

    /**
     * @param  Collection<int, PurchaseInvoiceLine>  $billed  document rows on this order, with their index
     */
    private function receiveOrder(PurchaseOrder $order, PurchaseInvoice $document, Collection $billed, User $actor, bool $errorOnLine, bool $requireReceivable): void
    {
        // Step 1: figures under lock, quantities still to receive on the billed lines
        $before = $this->synchronizer->lockedBalances($order);
        $lines = $this->linesToReceive($before, $billed->pluck('purchase_order_line_id')->unique());

        if ($lines === []) {
            return;
        }

        // Step 2: only an order that can take deliveries may need one
        if ($requireReceivable && ! $order->status->isReceivable()) {
            $key = $errorOnLine ? "lines.{$billed->keys()->first()}.purchase_order_line_id" : 'document_number';

            throw ValidationException::withMessages([$key => [__('purchase_invoices.order_not_receivable')]]);
        }

        // Step 3: one receipt dated as the document, linked to it
        $this->receipts->record($order, $before, [
            'receipt_date' => $document->document_date->toDateString(),
            'delivery_note_number' => null,
            'delivery_note_date' => null,
            'notes' => __('purchase_invoices.receipt_note', ['number' => $document->document_number]),
        ], $lines, $actor, $document->id);
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     * @param  Collection<int, int>  $lineIds
     * @return array<int, array{purchase_order_line_id: int, quantity: string}>
     */
    private function linesToReceive(Collection $balances, Collection $lineIds): array
    {
        $lines = [];

        foreach ($lineIds->sort() as $lineId) {
            $quantity = $balances->get($lineId)?->toReceive() ?? '0.000';

            if (bccomp($quantity, '0', 3) > 0) {
                $lines[] = ['purchase_order_line_id' => (int) $lineId, 'quantity' => $quantity];
            }
        }

        return $lines;
    }
}
