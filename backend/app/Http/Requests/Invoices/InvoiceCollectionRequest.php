<?php

namespace App\Http\Requests\Invoices;

use App\Enums\ResidualMode;
use App\Models\InvoiceInstallment;
use App\Services\Invoices\InvoiceAmountCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /invoice-installments/{installment}/collection (spec 0194, D-13): the
 * collected amount can not exceed the installment amount; a partial one needs
 * the residual mode (spec 0196, D-5). Authorization (invoices.collect) is checked
 * before the validation so a forbidden user never sees a 422.
 */
class InvoiceCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var InvoiceInstallment $installment */
        $installment = $this->route('installment');

        return $this->user()?->can('collect', $installment->loadMissing('invoice')->invoice) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var InvoiceInstallment $installment */
        $installment = $this->route('installment');

        $installment->loadMissing('invoice');
        // An already collected installment is refused by the service (409): no field rule must hide it
        $isOpen = bccomp((string) ($installment->collected_amount ?? '0'), '0', 2) <= 0;
        // Same cent rounding as InvoiceCollectionService::record, so rule and service agree on "partial"
        $isPartial = fn (): bool => $isOpen && is_numeric($this->input('collected_amount'))
            && bccomp(bcadd(app(InvoiceAmountCalculator::class)->normalize($this->input('collected_amount')), '0.005', 2), (string) $installment->amount, 2) < 0;

        return [
            'collected_amount' => ['required', 'numeric', 'gt:0', ...($isOpen ? ['max:'.$installment->amount] : [])],
            'collected_at' => ['required', 'date'],
            'residual_mode' => [Rule::requiredIf($isPartial), 'nullable', Rule::enum(ResidualMode::class)],
            'residual_due_date' => [
                Rule::requiredIf(fn (): bool => $isPartial() && $this->input('residual_mode') === ResidualMode::NewInstallment->value),
                'nullable', 'date', 'after_or_equal:'.$installment->invoice->document_date->toDateString(),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function payload(): array
    {
        return array_filter(
            $this->safe()->only(['collected_amount', 'collected_at', 'residual_mode', 'residual_due_date']),
            fn (mixed $value): bool => $value !== null,
        );
    }
}
