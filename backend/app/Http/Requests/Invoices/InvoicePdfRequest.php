<?php

declare(strict_types=1);

namespace App\Http\Requests\Invoices;

use App\Enums\DocumentLayoutModule;
use App\Models\DocumentLayout;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates GET /api/invoices/{invoice}/pdf (spec 0195 D-8): the optional
 * `layout_id` must be an ACTIVE layout of the `invoices` module. Authorization
 * (`invoices.view`) runs here, before validation, so a forbidden caller gets
 * 403 rather than a 422 hint.
 */
class InvoicePdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice && $this->user()?->can('view', $invoice) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'layout_id' => [
                'nullable', 'integer',
                Rule::exists('document_layouts', 'id')
                    ->where('module', DocumentLayoutModule::Invoices->value)
                    ->where('is_active', true),
            ],
        ];
    }

    public function layout(): ?DocumentLayout
    {
        $id = $this->validated('layout_id');

        return $id === null ? null : DocumentLayout::query()->findOrFail($id);
    }
}
