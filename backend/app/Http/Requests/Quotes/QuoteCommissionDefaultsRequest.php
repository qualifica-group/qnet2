<?php

namespace App\Http\Requests\Quotes;

use App\DataObjects\Commissions\QuoteCommissionDefaultsData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteCommissionDefaultsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'line_net_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'reference_date' => ['nullable', 'date'],
        ];
    }

    public function toData(): QuoteCommissionDefaultsData
    {
        return QuoteCommissionDefaultsData::fromValidated($this->validated());
    }
}
