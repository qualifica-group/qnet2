<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rules on the purchase orders a payable document bills (spec 0250, D-1, D-8):
 * same supplier and company as the document, an invoiceable status, and a
 * purchase order line on one document only. The orders involved are locked in
 * id order so two concurrent documents cannot bill the same line.
 */
final class PurchaseInvoiceLineGuard
{
    /**
     * Validates the payload and returns the ids of the orders to link (the
     * explicit ones plus the orders of the lines), ascending.
     *
     * @param  array<string, mixed>  $data  with supplier_id, company_id, lines and optional purchase_order_ids
     * @param  int|null  $ignoreInvoiceId  the document being edited: its own lines and orders stay valid
     * @return array<int, int>
     *
     * @throws ValidationException
     */
    public function assert(array $data, ?int $ignoreInvoiceId = null): array
    {
        // Step 1: the orders involved, from the payload and from the order lines
        $lines = array_values($data['lines']);
        $orderLines = $this->orderLines($lines);
        $explicitIds = array_map('intval', $data['purchase_order_ids'] ?? []);
        $orderIds = collect([...$explicitIds, ...$orderLines->pluck('purchase_order_id')->all()])
            ->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();

        // Step 2: lock them in id order, then judge
        $orders = $orderIds->isEmpty() ? collect() : PurchaseOrder::query()
            ->whereIn('id', $orderIds->all())->orderBy('id')->lockForUpdate()
            ->get(['id', 'supplier_id', 'company_id', 'status'])->keyBy('id');
        $linked = $this->alreadyLinked($ignoreInvoiceId);
        $errors = $this->orderErrors($orders, $data, $linked, $explicitIds);

        // Step 3: the lines
        $errors += $this->lineErrors($lines, $orderLines, $orders, $data, $linked, $ignoreInvoiceId);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $orderIds->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<int, PurchaseOrderLine>
     */
    private function orderLines(array $lines): Collection
    {
        $ids = array_values(array_filter(array_column($lines, 'purchase_order_line_id')));

        return $ids === [] ? collect() : PurchaseOrderLine::query()->whereIn('id', $ids)->get(['id', 'purchase_order_id'])->keyBy('id');
    }

    /**
     * @return array<int, int> order ids already linked to the document being edited
     */
    private function alreadyLinked(?int $invoiceId): array
    {
        return $invoiceId === null ? [] : DB::table('purchase_invoice_purchase_order')
            ->where('purchase_invoice_id', $invoiceId)->pluck('purchase_order_id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @param  Collection<int, PurchaseOrder>  $orders
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $linked
     * @param  array<int, int>  $explicitIds
     * @return array<string, array<int, string>>
     */
    private function orderErrors(Collection $orders, array $data, array $linked, array $explicitIds): array
    {
        foreach ($explicitIds as $id) {
            $message = $this->orderProblem($orders->get($id), $data, $linked);

            if ($message !== null) {
                return ['purchase_order_ids' => [$message]];
            }
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  Collection<int, PurchaseOrderLine>  $orderLines
     * @param  Collection<int, PurchaseOrder>  $orders
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $linked
     * @return array<string, array<int, string>>
     */
    private function lineErrors(array $lines, Collection $orderLines, Collection $orders, array $data, array $linked, ?int $ignoreInvoiceId): array
    {
        $taken = $this->invoicedElsewhere($orderLines->keys()->all(), $ignoreInvoiceId);
        $seen = [];
        $errors = [];

        foreach ($lines as $index => $line) {
            $orderLineId = (int) ($line['purchase_order_line_id'] ?? 0);

            if ($orderLineId === 0) {
                continue;
            }

            $orderLine = $orderLines->get($orderLineId);
            $message = $orderLine === null
                ? __('purchase_invoices.line_order_unknown')
                : $this->orderProblem($orders->get($orderLine->purchase_order_id), $data, $linked);

            if ($message === null && (in_array($orderLineId, $taken, true) || isset($seen[$orderLineId]))) {
                $message = __('purchase_invoices.line_already_invoiced');
            }

            $seen[$orderLineId] = true;

            if ($message !== null) {
                $errors["lines.{$index}.purchase_order_line_id"] = [$message];
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $linked
     */
    private function orderProblem(?PurchaseOrder $order, array $data, array $linked): ?string
    {
        return match (true) {
            $order === null => __('purchase_invoices.line_order_unknown'),
            $order->supplier_id !== (int) $data['supplier_id'] || $order->company_id !== (int) $data['company_id'] => __('purchase_invoices.order_party_mismatch'),
            ! in_array($order->id, $linked, true) && ! in_array($order->status, PurchaseInvoiceDraftBuilder::INVOICEABLE_STATUSES, true) => __('purchase_invoices.order_not_invoiceable'),
            default => null,
        };
    }

    /**
     * @param  array<int, int>  $orderLineIds
     * @return array<int, int>
     */
    private function invoicedElsewhere(array $orderLineIds, ?int $ignoreInvoiceId): array
    {
        if ($orderLineIds === []) {
            return [];
        }

        // Locking read: it sees the latest committed rows, not the transaction snapshot taken before the order locks.
        return PurchaseInvoiceLine::query()
            ->whereIn('purchase_order_line_id', $orderLineIds)
            ->lockForUpdate()
            ->when($ignoreInvoiceId !== null, fn ($query) => $query->where('purchase_invoice_id', '!=', $ignoreInvoiceId))
            ->pluck('purchase_order_line_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
