<?php

declare(strict_types=1);

namespace App\Services\Treasury;

use App\Models\InvoiceInstallment;
use App\Models\PurchaseInvoiceInstallment;
use App\Models\TreasuryMovement;
use Illuminate\Validation\ValidationException;

/**
 * Server-side lock of reconciled rows (spec 0363, D-2): a ticked movement, collection
 * or payment cannot be changed or cleared until the tick is removed.
 */
final class ReconciliationGuard
{
    public static function assertMovementFree(TreasuryMovement $movement): void
    {
        if ($movement->isReconciled()) {
            throw ValidationException::withMessages(['reconciled' => [__('treasury_reconciliations.movement_locked')]]);
        }
    }

    public static function assertInstallmentFree(InvoiceInstallment|PurchaseInvoiceInstallment $installment): void
    {
        if ($installment->reconciled_at !== null) {
            throw ValidationException::withMessages(['reconciled' => [__('treasury_reconciliations.installment_locked')]]);
        }
    }
}
