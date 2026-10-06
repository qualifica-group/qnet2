<?php

namespace App\Http\Requests\PaymentMethods;

use App\Enums\VatAllocation;
use App\Models\PaymentMethod;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Validation of the installment configuration shared by the Store/Update
 * payment method requests (spec 0194, D-10).
 */
trait ValidatesInstallmentConfig
{
    private const int INSTALLMENTS_MAX = 60;

    private const int DAYS_BETWEEN_MAX = 365;

    private const int EXTRA_DAYS_MAX = 31;

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function installmentRules(): array
    {
        return [
            'installments_count' => ['sometimes', 'integer', 'min:1', 'max:'.self::INSTALLMENTS_MAX],
            'days_between_installments' => [
                Rule::requiredIf(fn (): bool => (int) $this->input('installments_count', 1) > 1),
                'integer', 'min:0', 'max:'.self::DAYS_BETWEEN_MAX,
            ],
            'end_of_month' => ['sometimes', 'boolean'],
            'end_of_month_extra_days' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:'.self::EXTRA_DAYS_MAX],
            'vat_allocation' => ['sometimes', Rule::enum(VatAllocation::class)],
        ];
    }

    /**
     * `vat_first` needs at least 2 installments, judged on the effective values
     * (the submitted ones, else the persisted ones of the method being updated).
     */
    protected function assertVatAllocationFitsInstallments(Validator $validator, ?PaymentMethod $current): void
    {
        if ($validator->errors()->hasAny(['installments_count', 'vat_allocation'])) {
            return;
        }

        $allocation = $this->input('vat_allocation', $current?->vat_allocation?->value ?? VatAllocation::Split->value);
        $count = (int) $this->input('installments_count', $current?->installments_count ?? 1);

        if ($allocation === VatAllocation::VatFirst->value && $count < 2) {
            $validator->errors()->add('vat_allocation', 'The "vat_first" VAT allocation requires at least 2 installments.');
        }
    }
}
