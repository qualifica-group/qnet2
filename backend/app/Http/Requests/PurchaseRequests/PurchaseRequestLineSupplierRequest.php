<?php

namespace App\Http\Requests\PurchaseRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/purchase-request-lines/supplier (spec 0209, D-4): one
 * supplier for many lines. Who may change which line is decided by
 * PurchaseRequestLineSupplierService.
 */
class PurchaseRequestLineSupplierRequest extends FormRequest
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
            'line_ids' => ['required', 'array', 'min:1', 'max:'.PurchaseRequestLineStatusRequest::MAX_LINES],
            'line_ids.*' => ['required', 'integer', 'distinct', Rule::exists('purchase_request_lines', 'id')],
            'supplier_id' => ['required', 'integer', Rule::exists('registries', 'id')->where('is_supplier', true)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function lineIds(): array
    {
        return array_map('intval', $this->validated('line_ids'));
    }

    public function supplierId(): int
    {
        return (int) $this->validated('supplier_id');
    }
}
