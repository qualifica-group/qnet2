<?php

namespace App\Http\Requests\Invoices;

use App\Models\InvoiceLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of PATCH /api/invoices/{invoice}/lines/{line}/revenue-type
 * (spec 0350, D-8). Authorization is the `invoices.update` permission (InvoicePolicy),
 * never the state of the document; the sub-account must be active unless it is already on
 * the line.
 */
class UpdateInvoiceLineRevenueTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('invoice')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $line = $this->route('line');
        $currentId = $line instanceof InvoiceLine ? $line->revenue_type_id : null;

        return [
            'revenue_type_id' => ['present', 'nullable', 'integer', Rule::exists('revenue_types', 'id')->where(
                fn ($types) => $types->where('is_active', true)->orWhere('id', $currentId),
            )],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['revenue_type_id.exists' => __('invoices.revenue_type_inactive')];
    }

    public function revenueTypeId(): ?int
    {
        $id = $this->validated('revenue_type_id');

        return $id === null ? null : (int) $id;
    }
}
