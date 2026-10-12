<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\DataObjects\PurchaseInvoices\UpdatePurchaseInvoiceInstallmentData;
use App\Http\Resources\PurchaseInvoiceInstallmentResource;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use Illuminate\Support\Facades\DB;

/**
 * Edit the due date and payment method code of one installment (spec 0211,
 * D-7). Amounts only change by the payment redistribution, and an installment
 * with a payment is frozen.
 */
final class PurchaseInvoiceInstallmentUpdater
{
    public function handle(PurchaseInvoiceInstallment $installment, UpdatePurchaseInvoiceInstallmentData $data): PurchaseInvoiceInstallment
    {
        return DB::transaction(function () use ($installment, $data): PurchaseInvoiceInstallment {
            // Step 1: serialize on the document, as payments do
            PurchaseInvoice::query()->whereKey($installment->purchase_invoice_id)->lockForUpdate()->firstOrFail();
            $locked = PurchaseInvoiceInstallment::query()->findOrFail($installment->getKey());

            // Step 2: refuse once a payment is recorded
            if ($locked->isPaid()) {
                abort(409, 'Paid installments cannot be edited.');
            }

            // Step 3: write what was submitted
            $locked->update($data->attributes());

            return $locked->load(PurchaseInvoiceInstallmentResource::RELATIONS);
        });
    }
}
