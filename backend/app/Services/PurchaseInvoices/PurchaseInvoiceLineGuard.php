<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Rules on the purchase orders a payable document bills (spec 0250, D-1; spec
 * 0254, D-2): same supplier and company as the document, an invoiceable status,
 * and quantities within what the order line still allows. The orders involved
 * are locked in id order, then the quantity guard locks the order lines and
 * the document lines, so concurrent documents cannot bill beyond the limits.
 */
final class PurchaseInvoiceLineGuard
{
    public function __construct(private readonly PurchaseInvoiceQuantityGuard $quantities) {}

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

        // Step 3: the lines, then their quantities under lock (only the lines that passed)
        $lineErrors = $this->lineErrors($lines, $orderLines, $orders, $data, $linked);
        $errors += $lineErrors;
        $errors += $this->quantities->lineErrors(
            array_filter($lines, fn (int $index): bool => ! isset($lineErrors["lines.{$index}.purchase_order_line_id"]), ARRAY_FILTER_USE_KEY),
            $ignoreInvoiceId,
        );

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
    private function lineErrors(array $lines, Collection $orderLines, Collection $orders, array $data, array $linked): array
    {
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
}
