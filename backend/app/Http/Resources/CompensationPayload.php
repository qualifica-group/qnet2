<?php

namespace App\Http\Resources;

use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;

/**
 * The `compensation` object shared by active and passive installment payloads
 * (spec 0364): null when the installment is not compensated. The amount is the
 * one collected/paid by the compensation; the counterpart label names the
 * document on the other side. The relations are loaded here, only for the
 * rare compensated row, so no caller has to know about them.
 */
final class CompensationPayload
{
    /**
     * @return array{id: int, amount: string, counterpart_label: string}|null
     */
    public static function from(InvoiceInstallment|PurchaseInvoiceInstallment $installment): ?array
    {
        if ($installment->compensation_id === null) {
            return null;
        }

        $installment->loadMissing('compensation');
        $compensation = $installment->compensation;

        if ($installment instanceof InvoiceInstallment) {
            $compensation->loadMissing('purchaseInvoiceInstallment.purchaseInvoice:id,document_number');
            $label = (string) $compensation->purchaseInvoiceInstallment->purchaseInvoice->document_number;
        } else {
            $compensation->loadMissing('invoiceInstallment.invoice:id,number,year');
            $invoice = $compensation->invoiceInstallment->invoice;
            $label = "{$invoice->number}/{$invoice->year}";
        }

        return ['id' => $compensation->id, 'amount' => (string) $compensation->amount, 'counterpart_label' => $label];
    }
}
