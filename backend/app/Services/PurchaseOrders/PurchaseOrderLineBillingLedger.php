<?php

declare(strict_types=1);

namespace App\Services\PurchaseOrders;

use App\Enums\PurchaseInvoiceType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\PurchaseOrderLine;
use App\Support\Amounts\LineAmountCalculator;
use Illuminate\Support\Collection;

/**
 * The single source of ordered / received / billed quantities per purchase
 * order line (spec 0254): receipts, the passive-invoice guard, the draft
 * builder and the cost aggregator all read the balances from here.
 */
final class PurchaseOrderLineBillingLedger
{
    private const int SCALE = 3;

    private const string ZERO = '0.000';

    public function __construct(private readonly LineAmountCalculator $amounts) {}

    /**
     * Two queries whatever the number of lines. With `$lock` the order lines and
     * the referencing document lines are read FOR UPDATE, in id order; the caller
     * already holds the order lock (lock order: order, order lines, document lines).
     *
     * @param  array<int, int>  $lineIds
     * @param  int|null  $ignoreInvoiceId  document being edited: its own quantities do not count
     * @return Collection<int, PurchaseOrderLineBalance> keyed by purchase_order_line_id
     */
    public function forLines(array $lineIds, ?int $ignoreInvoiceId = null, bool $lock = false): Collection
    {
        $lineIds = array_values(array_unique($lineIds));

        if ($lineIds === []) {
            return collect();
        }

        // Step 1: order lines
        $lines = PurchaseOrderLine::query()
            ->whereIn('id', $lineIds)
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get(['id', 'quantity', 'received_quantity', 'residual_closed_at']);

        // Step 2: billed quantities per line and document type
        $billed = $this->billedQuantities($lineIds, $ignoreInvoiceId, $lock);

        return $lines->mapWithKeys(fn (PurchaseOrderLine $line): array => [
            $line->id => new PurchaseOrderLineBalance(
                lineId: $line->id,
                ordered: $this->quantity($line->quantity),
                received: $this->quantity($line->received_quantity),
                residualClosed: $line->residual_closed_at !== null,
                invoicedTotal: $billed[$line->id]['total'] ?? self::ZERO,
                invoicedInvoice: $billed[$line->id]['invoice'] ?? self::ZERO,
            ),
        ]);
    }

    /**
     * Rows are summed in PHP (not grouped in SQL) so the same query can be locked.
     * The document type comes from a subselect: only the lines are locked.
     *
     * @param  array<int, int>  $lineIds
     * @return array<int, array{total: string, invoice: string}>
     */
    private function billedQuantities(array $lineIds, ?int $ignoreInvoiceId, bool $lock): array
    {
        $rows = PurchaseInvoiceLine::query()
            ->whereIn('purchase_order_line_id', $lineIds)
            ->when($ignoreInvoiceId !== null, fn ($query) => $query->where('purchase_invoice_id', '!=', $ignoreInvoiceId))
            ->select(['id', 'purchase_order_line_id', 'quantity'])
            ->selectSub(
                PurchaseInvoice::query()->select('document_type')->whereColumn('purchase_invoices.id', 'purchase_invoice_lines.purchase_invoice_id'),
                'document_type',
            )
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();

        $billed = [];

        foreach ($rows as $row) {
            $lineId = (int) $row->purchase_order_line_id;
            $quantity = $this->quantity($row->quantity);
            $billed[$lineId] ??= ['total' => self::ZERO, 'invoice' => self::ZERO];
            $billed[$lineId]['total'] = bcadd($billed[$lineId]['total'], $quantity, self::SCALE);

            if ($row->document_type === PurchaseInvoiceType::Invoice->value) {
                $billed[$lineId]['invoice'] = bcadd($billed[$lineId]['invoice'], $quantity, self::SCALE);
            }
        }

        return $billed;
    }

    private function quantity(mixed $value): string
    {
        return bcadd($this->amounts->normalize($value), '0', self::SCALE);
    }
}
