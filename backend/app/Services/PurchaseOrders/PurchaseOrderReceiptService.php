<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseOrderReceipt;
use App\Models\PurchaseOrderReceiptLine;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Deliveries of a purchase order (spec 0254, D-1, D-4, D-5, D-8). The service
 * is the only writer of purchase_order_lines.received_quantity (D-10): every
 * write happens under the order lock and the figures are re-read after the
 * lines are locked, so no check-then-act window is left.
 */
final class PurchaseOrderReceiptService
{
    private const int SCALE = 3;

    public function __construct(private readonly PurchaseOrderFulfillmentSynchronizer $synchronizer) {}

    /**
     * @param  array<string, mixed>  $header  receipt_date, delivery_note_number, delivery_note_date, notes
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     */
    public function create(PurchaseOrder $order, array $header, array $lines, User $actor): PurchaseOrderReceipt
    {
        return DB::transaction(function () use ($order, $header, $lines, $actor): PurchaseOrderReceipt {
            // Step 1: serialize on the order; only a receivable one accepts deliveries (409)
            $locked = $this->lock($order);

            if (! $locked->status->isReceivable()) {
                abort(409, __('purchase_orders.not_receivable'));
            }

            // Step 2: lock the lines and validate against the fresh figures (422)
            $before = $this->synchronizer->lockedBalances($locked);
            $this->assertReceivable($before, $lines);

            // Step 3: persist the receipt, move the quantities, follow with status and request lines
            return $this->record($locked, $before, $header, $lines, $actor);
        });
    }

    /**
     * Writes a validated receipt of an order already locked, with its lines locked
     * and read as `$before` (lockedBalances). Shared with the receipt an invoice
     * generates (D-12); runs inside the caller's transaction.
     *
     * @param  Collection<int, PurchaseOrderLineBalance>  $before
     * @param  array<string, mixed>  $header
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     * @param  int|null  $invoiceId  the payable document that generated the receipt
     */
    public function record(PurchaseOrder $locked, Collection $before, array $header, array $lines, User $actor, ?int $invoiceId = null): PurchaseOrderReceipt
    {
        // Step 1: the receipt and the received quantities
        $receipt = $this->persist($locked, $header, $lines, $actor, $invoiceId);
        $this->applyDeltas($before, $this->deltas($lines));

        // Step 2: status and request lines follow the lines
        $this->synchronizer->sync($locked, $before, $actor);

        return $receipt;
    }

    /** Cancel a receipt; 409 when the order is cancelled or a line would drop below what is invoiced. */
    public function delete(PurchaseOrder $order, PurchaseOrderReceipt $receipt, User $actor): void
    {
        DB::transaction(function () use ($order, $receipt, $actor): void {
            // Step 1: serialize on the order
            $locked = $this->lock($order);

            if ($locked->status === PurchaseOrderStatus::Cancelled) {
                abort(409, __('purchase_orders.cancelled'));
            }

            // Step 2: lock the lines and check the invoiced quantities (409)
            $before = $this->synchronizer->lockedBalances($locked);
            $receipt = $locked->receipts()->whereKey($receipt->getKey())->firstOrFail();
            $receiptLines = $receipt->lines()->get();
            $deltas = $this->reversal($receiptLines);
            $this->assertNotBelowInvoiced($before, $deltas);

            // Step 3: remove the receipt (line by line, to keep the audit log) and its quantities
            $receiptLines->each(fn (PurchaseOrderReceiptLine $line): ?bool => $line->delete());
            $receipt->delete();
            $this->applyDeltas($before, $deltas);

            // Step 4: status and request lines follow the lines
            $this->synchronizer->sync($locked, $before, $actor);
        });
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     */
    private function assertReceivable(Collection $balances, array $lines): void
    {
        $errors = [];

        foreach ($lines as $index => $line) {
            $balance = $balances[(int) $line['purchase_order_line_id']] ?? null;

            if ($balance === null || $balance->residualClosed) {
                $errors["lines.{$index}.purchase_order_line_id"] = [__('purchase_orders.line_not_receivable')];
            } elseif (bccomp($line['quantity'], $balance->residual(), self::SCALE) > 0) {
                $errors["lines.{$index}.quantity"] = [__('purchase_orders.quantity_exceeds_residual', ['residual' => $balance->residual()])];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Annulling must not leave a line received below what is already invoiced
     * (invoice documents), nor below the billed quantity once its residual is
     * closed (the ceiling is then the received quantity).
     *
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances
     * @param  array<int, string>  $deltas  line id => signed quantity
     */
    private function assertNotBelowInvoiced(Collection $balances, array $deltas): void
    {
        foreach ($deltas as $lineId => $delta) {
            $balance = $balances[$lineId];
            $received = bcadd($balance->received, $delta, self::SCALE);
            $floor = $balance->residualClosed ? $balance->invoicedTotal : $balance->invoicedInvoice;

            if (bccomp($received, $floor, self::SCALE) < 0) {
                abort(409, __('purchase_orders.receipt_below_invoiced'));
            }
        }
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     */
    private function persist(PurchaseOrder $order, array $header, array $lines, User $actor, ?int $invoiceId): PurchaseOrderReceipt
    {
        $receipt = new PurchaseOrderReceipt($header);
        $receipt->forceFill([
            'purchase_order_id' => $order->id,
            'purchase_invoice_id' => $invoiceId,
            'created_by' => $actor->id,
        ])->save();

        foreach ($lines as $line) {
            $receipt->lines()->create([
                'purchase_order_line_id' => (int) $line['purchase_order_line_id'],
                'quantity' => $line['quantity'],
            ]);
        }

        return $receipt;
    }

    /**
     * @param  array<int, array{purchase_order_line_id: int, quantity: string}>  $lines
     * @return array<int, string> line id => summed quantity
     */
    private function deltas(array $lines): array
    {
        $deltas = [];

        foreach ($lines as $line) {
            $lineId = (int) $line['purchase_order_line_id'];
            $deltas[$lineId] = bcadd($deltas[$lineId] ?? '0', $line['quantity'], self::SCALE);
        }

        return $deltas;
    }

    /**
     * @param  Collection<int, PurchaseOrderReceiptLine>  $receiptLines
     * @return array<int, string> line id => negative quantity
     */
    private function reversal(Collection $receiptLines): array
    {
        $deltas = [];

        foreach ($receiptLines as $line) {
            $lineId = (int) $line->purchase_order_line_id;
            $deltas[$lineId] = bcsub($deltas[$lineId] ?? '0', (string) $line->quantity, self::SCALE);
        }

        return $deltas;
    }

    /**
     * @param  Collection<int, PurchaseOrderLineBalance>  $balances  figures read under the line locks
     * @param  array<int, string>  $deltas  line id => signed quantity
     */
    private function applyDeltas(Collection $balances, array $deltas): void
    {
        foreach ($deltas as $lineId => $delta) {
            PurchaseOrderLine::query()->whereKey($lineId)->update([
                'received_quantity' => bcadd($balances[$lineId]->received, $delta, self::SCALE),
            ]);
        }
    }

    private function lock(PurchaseOrder $order): PurchaseOrder
    {
        return PurchaseOrder::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
    }
}
