<?php

declare(strict_types=1);

namespace App\Services\Commissions\Settlements;

use App\Enums\InvoicePaymentStatus;
use App\Models\CommissionSettlement;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\Invoices\InvoicePaymentStatusResolver;
use Illuminate\Support\Carbon;

/**
 * The "purchase_invoice" block of the settlement detail (spec 0267, D-5): the
 * linked payable invoice with what was paid and its payment status, derived
 * from its installments (never stored on the settlement). The amounts follow
 * the `net_payable` field permission.
 */
final class SettlementInvoiceView
{
    public function __construct(private readonly InvoicePaymentStatusResolver $resolver) {}

    /**
     * @return array<string, mixed>|null
     */
    public function present(CommissionSettlement $settlement, bool $amountsVisible): ?array
    {
        $invoice = $settlement->purchaseInvoice;

        if ($invoice === null) {
            return null;
        }

        $payable = $invoice->payableAmount();
        $paid = $invoice->installments->reduce(fn (string $sum, PurchaseInvoiceInstallment $installment): string => bcadd($sum, (string) ($installment->paid_amount ?? '0'), 2), '0.00');

        $payload = [
            'id' => $invoice->id,
            'document_number' => $invoice->document_number,
            'document_date' => $invoice->document_date->format('Y-m-d'),
            'payable_amount' => $payable,
            'paid_amount' => $paid,
            'residual_amount' => bcsub($payable, $paid, 2),
            'payment_status' => $this->paymentStatus($settlement)->value,
        ];

        return $amountsVisible ? $payload : array_diff_key($payload, array_flip(['payable_amount', 'paid_amount', 'residual_amount']));
    }

    /**
     * Paid when no installment is left unpaid, else the state of the first
     * unpaid one by sequence; no schedule means nothing due (as the grids do).
     */
    private function paymentStatus(CommissionSettlement $settlement): InvoicePaymentStatus
    {
        $installments = $settlement->purchaseInvoice->installments->sortBy('sequence');
        $firstUnpaid = $installments->first(fn (PurchaseInvoiceInstallment $installment): bool => ! $installment->isPaid());

        if ($firstUnpaid === null) {
            return $installments->isEmpty() ? InvoicePaymentStatus::NotDue : InvoicePaymentStatus::Paid;
        }

        return $this->resolver->forDueDate($firstUnpaid->due_date, Carbon::today());
    }
}
