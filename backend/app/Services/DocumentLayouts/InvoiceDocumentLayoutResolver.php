<?php

declare(strict_types=1);

namespace App\Services\DocumentLayouts;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\Invoice;

/**
 * Resolves WHICH DocumentLayout an Invoice's PDF is generated against (spec
 * 0196, D-2), same rule as QuoteDocumentLayoutResolver: the invoice's own
 * `layout_id` wins even when the layout was deactivated afterwards, else the
 * `invoices` module's active default. Returns null when neither exists; the
 * caller maps that to the existing 422.
 */
final class InvoiceDocumentLayoutResolver
{
    public function resolve(Invoice $invoice): ?DocumentLayout
    {
        // Step 1: an explicit layout always wins, active or not.
        if ($invoice->layout_id !== null) {
            return $invoice->layout()->first();
        }

        // Step 2: fall back to the invoices module's current active default.
        return DocumentLayout::activeDefaultFor(DocumentLayoutModule::Invoices);
    }
}
