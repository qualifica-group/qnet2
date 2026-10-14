<?php

namespace App\Http\Requests\PurchaseInvoices;

use App\Enums\ResidualMode;
use App\Models\FinancialAccount;
use App\Models\PurchaseInvoiceInstallment;
use App\Support\Amounts\LineAmountCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /purchase-invoice-installments/{installment}/payment (spec 0211, D-5/D-6):
 * the paid amount can not exceed the installment amount; a partial one needs
 * the residual mode. Authorization (purchase-invoices.pay) is checked before
 * the validation so a forbidden user never sees a 422. The account/company
 * match is checked by the service, under the document lock.
 */
class PurchaseInvoicePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pay', $this->installment()->loadMissing('purchaseInvoice')->purchaseInvoice) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $installment = $this->installment()->loadMissing('purchaseInvoice');
        // An already paid installment is refused by the service (409): no field rule must hide it
        $isOpen = ! $installment->isPaid();
        // Same cent rounding as PurchaseInvoicePaymentService::record, so rule and service agree on "partial"
        $isPartial = fn (): bool => $isOpen && is_numeric($this->input('paid_amount'))
            && app(LineAmountCalculator::class)->isPartial($this->input('paid_amount'), $installment->amount);

        return [
            'paid_amount' => ['required', 'numeric', 'gt:0', ...($isOpen ? ['max:'.$installment->amount] : [])],
            'paid_at' => ['required', 'date'],
            'financial_account_id' => ['nullable', 'integer', Rule::exists(FinancialAccount::class, 'id')],
            'residual_mode' => [Rule::requiredIf($isPartial), 'nullable', Rule::enum(ResidualMode::class)],
            'residual_due_date' => [
                Rule::requiredIf(fn (): bool => $isPartial() && $this->input('residual_mode') === ResidualMode::NewInstallment->value),
                'nullable', 'date', 'after_or_equal:'.$installment->purchaseInvoice->document_date->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_filter(
            $this->safe()->only(['paid_amount', 'paid_at', 'financial_account_id', 'residual_mode', 'residual_due_date']),
            fn (mixed $value): bool => $value !== null,
        );
    }

    private function installment(): PurchaseInvoiceInstallment
    {
        /** @var PurchaseInvoiceInstallment $installment */
        $installment = $this->route('installment');

        return $installment;
    }
}
