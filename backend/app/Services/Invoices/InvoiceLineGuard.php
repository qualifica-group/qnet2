<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\InvoiceLine;
use App\Models\WorkOrder;
use Illuminate\Validation\ValidationException;

/**
 * Rules on the offer lines of an invoice payload: they must belong to the work
 * order of the request and a line can sit in one document only (spec 0243, D-2).
 */
final class InvoiceLineGuard
{
    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @param  int|null  $quoteId  the offer of the document: its lines are the only ones allowed (spec 0267, D-6)
     * @param  int|null  $ignoreInvoiceId  the document being edited: its own lines do not count
     */
    public function assert(array $lines, ?int $workOrderId, ?int $quoteId, ?int $ignoreInvoiceId = null): void
    {
        $allowed = $workOrderId === null
            ? []
            : WorkOrder::query()->findOrFail($workOrderId, ['id'])->quoteLines()->when($quoteId !== null, fn ($query) => $query->where('quote_lines.quote_id', $quoteId))->pluck('quote_lines.id')->all();
        $taken = $this->invoicedElsewhere($lines, $ignoreInvoiceId);
        $errors = [];

        foreach (array_values($lines) as $index => $line) {
            if (empty($line['quote_line_id'])) {
                continue;
            }

            $quoteLineId = (int) $line['quote_line_id'];

            if (! in_array($quoteLineId, $allowed, true)) {
                $errors["lines.{$index}.quote_line_id"] = ['The line does not belong to the work order of this request.'];
            } elseif (in_array($quoteLineId, $taken, true)) {
                $errors["lines.{$index}.quote_line_id"] = [__('invoices.line_already_invoiced')];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, int>
     */
    private function invoicedElsewhere(array $lines, ?int $ignoreInvoiceId): array
    {
        $ids = array_filter(array_column($lines, 'quote_line_id'));

        if ($ids === []) {
            return [];
        }

        return InvoiceLine::query()
            ->whereIn('quote_line_id', $ids)
            ->when($ignoreInvoiceId !== null, fn ($query) => $query->where('invoice_id', '!=', $ignoreInvoiceId))
            ->pluck('quote_line_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
