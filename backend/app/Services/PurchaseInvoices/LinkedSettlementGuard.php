<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Models\PurchaseInvoice;

/**
 * A payable document born from an agent commission settlement is owned by it
 * (spec 0267, D-5): edit, delete and number change are refused; notes,
 * attachments, payments and the due date/method of unpaid installments stay.
 */
final class LinkedSettlementGuard
{
    public function isLinked(PurchaseInvoice $document): bool
    {
        if ($document->relationLoaded('commissionSettlement')) {
            return $document->commissionSettlement !== null;
        }

        return $document->commissionSettlement()->exists();
    }

    public function assertNotLinked(PurchaseInvoice $document): void
    {
        if ($this->isLinked($document)) {
            abort(409, __('purchase_invoices.settlement_locked'));
        }
    }
}
