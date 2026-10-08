<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\DataObjects\Invoices\UpdateInvoiceInstallmentData;
use App\Http\Resources\InvoiceInstallmentResource;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use Illuminate\Support\Facades\DB;

/**
 * Edit the due date and payment method code of one installment (spec 0197,
 * D-6). Amounts only change by rebalancing the invoice, and an installment
 * with a collection is frozen.
 */
final class InvoiceInstallmentUpdater
{
    public function handle(InvoiceInstallment $installment, UpdateInvoiceInstallmentData $data): InvoiceInstallment
    {
        return DB::transaction(function () use ($installment, $data): InvoiceInstallment {
            // Step 1: serialize on the invoice, as collections and rebalancing do
            Invoice::query()->whereKey($installment->invoice_id)->lockForUpdate()->firstOrFail();
            $locked = InvoiceInstallment::query()->findOrFail($installment->getKey());

            // Step 2: refuse once a collection is recorded
            $this->assertNotCollected($locked);

            // Step 3: write what was submitted
            $locked->update($data->attributes());

            return $locked->load(InvoiceInstallmentResource::RELATIONS);
        });
    }

    private function assertNotCollected(InvoiceInstallment $installment): void
    {
        if (bccomp((string) ($installment->collected_amount ?? '0'), '0', 2) > 0) {
            abort(409, 'Collected installments cannot be edited.');
        }
    }
}
