<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

use App\Enums\PurchaseInvoiceType;
use App\Models\CostType;
use App\Models\PaymentMethod;
use App\Models\Payslip;
use App\Models\PurchaseInvoice;
use App\Models\User;
use App\Services\PurchaseInvoices\PurchaseInvoiceAmountCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Writes the payable document of a payslip (spec 0431, D-1, D-3): one line on
 * the payroll cost type without VAT, one installment equal to the net due on
 * the payment date. Amounts come from the same calculator as every payable
 * document; the schedule is not planned from the payment method terms because
 * the date is chosen at import time.
 */
final class PayslipDocumentWriter
{
    private ?CostType $costType = null;

    private ?PaymentMethod $paymentMethod = null;

    public function __construct(private readonly PurchaseInvoiceAmountCalculator $amounts) {}

    public function create(PayslipEntry $entry, int $supplierId, CarbonImmutable $paymentDate, User $actor): void
    {
        // Step 1: the document with its single line
        $computed = $this->computed($entry);
        $document = PurchaseInvoice::query()->create([
            'document_type' => PurchaseInvoiceType::Payslip,
            'company_id' => $entry->companyId,
            'supplier_id' => $supplierId,
            'document_number' => null,
            'document_date' => CarbonImmutable::createFromFormat('!Y-m', $entry->period)->endOfMonth()->toDateString(),
            'payment_method_id' => $this->paymentMethod()->id,
            'financial_account_id' => null,
            'withholding_amount' => '0.00',
            'enasarco_amount' => '0.00',
            'created_by' => $actor->id,
        ] + $this->totals($computed));
        $document->lines()->createMany($computed['lines']);

        // Step 2: the installment due on the payment date
        $document->installments()->create([
            'sequence' => 1,
            'due_date' => $paymentDate->toDateString(),
            'amount' => $computed['total'],
            'payment_method_code' => $this->paymentMethod()->payment_method_code,
        ]);

        // Step 3: the payroll detail
        Payslip::query()->create([
            'purchase_invoice_id' => $document->id,
            'company_id' => $entry->companyId,
            'user_id' => $entry->userId,
            'period' => $entry->period,
            'payslip_type' => $entry->payslipType,
            'employee_code' => $entry->employeeCode,
        ]);
    }

    /**
     * Net, line, installment and due date of an unpaid payslip.
     */
    public function update(PayslipEntry $entry, CarbonImmutable $paymentDate): void
    {
        $payslip = $entry->existing;
        $document = $payslip->purchaseInvoice;
        $computed = $this->computed($entry);

        // Step 1: totals and line
        $document->update($this->totals($computed));
        $document->lines()->delete();
        $document->lines()->createMany($computed['lines']);

        // Step 2: a single installment with the new amount and date
        $installments = $document->installments;
        $first = $installments->first();
        $document->installments()->whereKeyNot($first->id)->delete();
        $first->update(['amount' => $computed['total'], 'due_date' => $paymentDate->toDateString()]);

        // Step 3: the consultant's employee code
        $payslip->update(['employee_code' => $entry->employeeCode]);
    }

    /**
     * @return array{lines: array<int, array<string, mixed>>, net: string, vat: string, total: string}
     */
    private function computed(PayslipEntry $entry): array
    {
        $period = CarbonImmutable::createFromFormat('!Y-m', $entry->period)->locale(app()->getLocale())->translatedFormat('F Y');

        return $this->amounts->compute([[
            'description' => __('payslips.import.line_description', ['period' => $period]),
            'quantity' => '1',
            'unit_price' => $entry->net(),
            'vat_rate_id' => null,
            'cost_type_id' => $this->costType()->id,
        ]]);
    }

    /**
     * @param  array{net: string, vat: string, total: string}  $computed
     * @return array<string, string>
     */
    private function totals(array $computed): array
    {
        return ['net_amount' => $computed['net'], 'vat_amount' => $computed['vat'], 'total_amount' => $computed['total']];
    }

    private function costType(): CostType
    {
        return $this->costType ??= CostType::query()->where('code', config('payroll.payslip.cost_type_code'))->first()
            ?? $this->refuse('cost_type_missing');
    }

    private function paymentMethod(): PaymentMethod
    {
        return $this->paymentMethod ??= PaymentMethod::query()->where('code', config('payroll.payslip.payment_method_code'))->first()
            ?? $this->refuse('payment_method_missing');
    }

    private function refuse(string $messageKey): never
    {
        throw ValidationException::withMessages(['file' => [__("payslips.import.errors.{$messageKey}")]]);
    }
}
