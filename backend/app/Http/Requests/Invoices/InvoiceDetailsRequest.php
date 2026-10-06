<?php

namespace App\Http\Requests\Invoices;

use App\Enums\DocumentLayoutModule;
use App\Enums\InvoiceTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /invoices/{invoice}/details (spec 0194, D-14): external number/date,
 * tag, deviation and internal note. Authorization stays in the controller.
 */
class InvoiceDetailsRequest extends FormRequest
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
            'external_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'external_date' => ['nullable', 'date', 'required_with:external_number'],
            'tag' => ['sometimes', 'nullable', Rule::enum(InvoiceTag::class)],
            'deviation' => ['sometimes', 'nullable', 'numeric', 'between:-9999999999,9999999999'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:'.InvoiceWriteRequest::NOTES_MAX_LENGTH],
            'layout_id' => ['sometimes', 'nullable', 'integer', Rule::exists('document_layouts', 'id')->where('module', DocumentLayoutModule::Invoices->value)->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->safe()->all();
    }
}
