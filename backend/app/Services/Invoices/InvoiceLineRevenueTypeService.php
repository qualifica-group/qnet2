<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changes only the revenue sub-account of one invoice line (spec 0350, D-2/D-8):
 * a managerial datum that is not on the fiscal document, so it is allowed on a
 * registered invoice and on one with collections; it never touches amounts or
 * other lines. The change is logged on the invoice.
 */
class InvoiceLineRevenueTypeService
{
    public function assign(Invoice $invoice, InvoiceLine $line, ?int $revenueTypeId, User $actor): InvoiceLine
    {
        return DB::transaction(function () use ($invoice, $line, $revenueTypeId, $actor): InvoiceLine {
            // Step 1: nothing to do (and nothing to log) when the value does not change
            $previous = $line->revenue_type_id;

            if ($previous !== $revenueTypeId) {
                // Step 2: write the column only
                $line->revenue_type_id = $revenueTypeId;
                $line->saveQuietly();

                // Step 3: audit entry on the document
                activity($invoice->getTable())
                    ->performedOn($invoice)
                    ->causedBy($actor)
                    ->event('line-revenue-type.updated')
                    ->withProperties(['line_id' => $line->id, 'old' => ['revenue_type_id' => $previous], 'new' => ['revenue_type_id' => $revenueTypeId]])
                    ->log('Invoice line revenue type updated');
            }

            return $line->fresh('revenueType.chartAccount.parent');
        });
    }
}
