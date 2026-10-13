<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Payload of POST /api/products/cost-type (spec 0251, D-6): one cost type (or
 * none) for many products. Gated by `products.update`.
 */
class SetProductsCostTypeRequest extends FormRequest
{
    public const int MAX_PRODUCTS = 500;

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
            'product_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PRODUCTS],
            'product_ids.*' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')],
            'cost_type_id' => ['present', 'nullable', 'integer', Rule::exists('cost_types', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function productIds(): array
    {
        return array_map('intval', $this->validated('product_ids'));
    }

    public function costTypeId(): ?int
    {
        $costTypeId = $this->validated('cost_type_id');

        return $costTypeId === null ? null : (int) $costTypeId;
    }
}
