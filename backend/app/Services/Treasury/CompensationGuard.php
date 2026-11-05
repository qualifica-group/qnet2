<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Validation\ValidationException;

/**
 * A compensated installment is changed only by cancelling its compensation (spec 0364, D-7).
 * Applied on the collection/payment endpoints; the compensation service skips it on purpose.
 */
final class CompensationGuard
{
    public static function assertInstallmentFree(InvoiceInstallment|PurchaseInvoiceInstallment $installment): void
    {
        if ($installment->compensation_id !== null) {
            throw ValidationException::withMessages(['compensation' => [__('treasury_compensations.installment_locked')]]);
        }
    }
}
