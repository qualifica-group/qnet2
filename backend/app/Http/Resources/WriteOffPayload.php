<?php

namespace App\Http\Resources;

use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;

/**
 * The `write_off` object shared by active and passive installment payloads
 * (spec 0361): null when the installment has no write-off.
 */
final class WriteOffPayload
{
    /**
     * @return array{amount: string, reason: string, note: string|null}|null
     */
    public static function from(InvoiceInstallment|PurchaseInvoiceInstallment $installment): ?array
    {
        if ($installment->write_off_amount === null) {
            return null;
        }

        return [
            'amount' => $installment->write_off_amount,
            'reason' => $installment->write_off_reason->value,
            'note' => $installment->write_off_note,
        ];
    }
}
