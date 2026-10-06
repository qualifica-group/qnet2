<?php

namespace App\Http\Requests\Invoices;

use App\Models\InvoiceInstallment;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /invoice-installments/{installment}/collection (spec 0194, D-13): the
 * collected amount can not exceed the installment amount. Authorization
 * (invoices.collect) stays in the controller.
 */
class InvoiceCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var InvoiceInstallment $installment */
        $installment = $this->route('installment');

        return [
            'collected_amount' => ['required', 'numeric', 'gt:0', 'max:'.$installment->amount],
            'collected_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array{collected_amount: string, collected_at: string}
     */
    public function payload(): array
    {
        /** @var array{collected_amount: string, collected_at: string} */
        return $this->safe()->only(['collected_amount', 'collected_at']);
    }
}
