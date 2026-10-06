<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /invoices/monthly-summary (spec 0194, D-15). Authorization (viewAny)
 * stays in the controller.
 */
class InvoiceMonthlySummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'between:2000,2100'],
            'type' => ['sometimes', Rule::in(['all', 'proforma', 'invoice'])],
        ];
    }

    public function year(): int
    {
        return (int) $this->safe()->only(['year'])['year'];
    }

    public function type(): string
    {
        return (string) ($this->safe()->only(['type'])['type'] ?? 'all');
    }
}
