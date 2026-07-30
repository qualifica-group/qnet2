<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/quotes/commission-recipients: the admissible recipient
 * of each commission role for one quote line's product. Same shape as
 * QuoteCommissionDefaultsRequest minus the economic inputs — this endpoint
 * resolves identities, never amounts.
 */
class QuoteCommissionRecipientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via QuotePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'quote_id' => ['nullable', 'integer', Rule::exists('quotes', 'id')],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
        ];
    }
}
