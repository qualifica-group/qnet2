<?php

declare(strict_types=1);

namespace App\Services\PurchaseInvoices;

use App\Exceptions\Invoices\InvalidInstallmentConfigurationException;
use App\Models\PaymentMethod;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceInstallment;
use App\Services\Invoices\InstallmentScheduleCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Adapter over the pure InstallmentScheduleCalculator (spec 0211, D-3): the
 * schedule of a payable document comes from its payment method, exactly as for
 * the active invoices. An unusable payment method configuration becomes a 422
 * on `payment_method_id`.
 */
final class PurchaseInvoiceInstallmentPlanner
{
    public function __construct(private readonly InstallmentScheduleCalculator $calculator) {}

    /**
     * @return array<int, array{sequence: int, due_date: CarbonImmutable, amount: string, payment_method_code: string|null}>
     */
    public function plan(CarbonImmutable $documentDate, PaymentMethod $method, string $net, string $vat, string $total): array
    {
        try {
            return $this->calculator->calculate($documentDate, $method, $net, $vat, $total);
        } catch (InvalidInstallmentConfigurationException $exception) {
            throw ValidationException::withMessages(['payment_method_id' => [$exception->getMessage()]]);
        }
    }

    /**
     * Replace the schedule of the document with the one its payment method
     * and amounts produce; the installments sum to the document total.
     *
     * @return Collection<int, PurchaseInvoiceInstallment>
     */
    public function generate(PurchaseInvoice $invoice): Collection
    {
        $schedule = $this->plan(
            CarbonImmutable::parse($invoice->document_date),
            $invoice->paymentMethod()->firstOrFail(),
            (string) $invoice->net_amount,
            (string) $invoice->vat_amount,
            (string) $invoice->total_amount,
        );

        $invoice->installments()->delete();
        $invoice->installments()->createMany($schedule);

        return $invoice->installments()->get();
    }
}
