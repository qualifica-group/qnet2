<?php

declare(strict_types=1);

namespace App\Services\Invoices;

use App\Enums\InstallmentStatus;
use App\Enums\InvoicePaymentStatus;
use App\Models\InvoiceInstallment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Computed payment status of an invoice (spec 0194, D-13), never persisted:
 * paid when every installment is paid, otherwise the state of the first unpaid
 * installment by sequence (not due / overdue 1..21 days / seriously overdue).
 */
final class InvoicePaymentStatusResolver
{
    /**
     * @param  Collection<int, InvoiceInstallment>  $installments
     */
    public function resolve(Collection $installments, CarbonInterface $today): InvoicePaymentStatus
    {
        $first = $installments->sortBy('sequence')
            ->first(fn (InvoiceInstallment $installment): bool => $installment->status() !== InstallmentStatus::Paid);

        if ($first === null) {
            return $installments->isEmpty() ? InvoicePaymentStatus::NotDue : InvoicePaymentStatus::Paid;
        }

        return $this->forDueDate($first->due_date, $today);
    }

    /** Status of an UNPAID installment due on `$dueDate`. */
    public function forDueDate(CarbonInterface $dueDate, CarbonInterface $today): InvoicePaymentStatus
    {
        $daysLate = (int) $dueDate->startOfDay()->diffInDays($today->startOfDay(), false);

        return match (true) {
            $daysLate <= 0 => InvoicePaymentStatus::NotDue,
            $daysLate > InvoicePaymentStatus::SERIOUSLY_OVERDUE_DAYS => InvoicePaymentStatus::SeriouslyOverdue,
            default => InvoicePaymentStatus::Overdue,
        };
    }
}
