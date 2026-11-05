<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/products/revenue-type (spec 0350): one revenue
 * sub-account (or none) for many products. Gated by `products.update`.
 */
class SetProductsRevenueTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('products.update') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.SetProductsCostTypeRequest::MAX_PRODUCTS],
            'ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'revenue_type_id' => ['present', 'nullable', 'integer', Rule::exists('revenue_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function productIds(): array
    {
        return array_map('intval', $this->validated('ids'));
    }

    public function revenueTypeId(): ?int
    {
        $revenueTypeId = $this->validated('revenue_type_id');

        return $revenueTypeId === null ? null : (int) $revenueTypeId;
    }
}
