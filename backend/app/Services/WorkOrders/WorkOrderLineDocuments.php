<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\WorkOrder;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Illuminate\Support\Carbon;

/**
 * The "Documento" of each line of a commessa (spec 0403, D-3): the most recent
 * proforma or invoice of THIS commessa that bills the line, with its computed
 * payment status. One query for all the lines.
 */
final class WorkOrderLineDocuments
{
    public function __construct(private readonly InvoicePaymentStatusResolver $statusResolver) {}

    /**
     * @param  array<int, int>  $lineIds  quote line ids
     * @return array<int, array{id: int, type: string, number_label: string, payment_status: string}> by quote line id; lines without a document are absent
     */
    public function forLines(WorkOrder $workOrder, array $lineIds): array
    {
        if ($lineIds === []) {
            return [];
        }

        $invoices = Invoice::query()
            ->where('work_order_id', $workOrder->id)
            ->whereHas('lines', fn ($query) => $query->whereIn('quote_line_id', $lineIds))
            ->with([
                'installments',
                'lines' => fn ($query) => $query->select(['id', 'invoice_id', 'quote_line_id'])->whereIn('quote_line_id', $lineIds),
            ])
            ->orderByDesc('document_date')
            ->orderByDesc('id')
            ->get();

        $today = Carbon::today();
        $documents = [];

        foreach ($invoices as $invoice) {
            $payload = null;

            /** @var InvoiceLine $line */
            foreach ($invoice->lines as $line) {
                if (isset($documents[$line->quote_line_id])) {
                    continue;
                }

                $payload ??= [
                    'id' => $invoice->id,
                    'type' => $invoice->type->value,
                    'number_label' => "{$invoice->number}/{$invoice->year}",
                    'payment_status' => $this->statusResolver->resolve($invoice->installments, $today)->value,
                ];
                $documents[$line->quote_line_id] = $payload;
            }
        }

        return $documents;
    }
}
