<?php

namespace App\Http\Requests\Products;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query of GET /api/products/supplier-commissions (spec 0320, D-21): a saved
 * `product_id` XOR the `category_id` of a product being created, plus the
 * suppliers to look up. Open to whoever edits products or picks them on the
 * lines that carry a supplier (offers, commessa costs): the for-select feeding
 * those pickers has no permission of its own, so a bare authenticated user is
 * not enough here, the answer exposes commission rules.
 */
class ProductSupplierCommissionsRequest extends FormRequest
{
    public const int MAX_SUPPLIERS = 50;

    /** Any of these lets the actor see which suppliers carry a commission on a product. */
    private const array ALLOWED_PERMISSIONS = [
        'products.viewAny',
        'products.create',
        'products.update',
        'quotes.create',
        'quotes.update',
        'work-orders.manageCosts',
    ];

    public function authorize(): bool
    {
        $user = $this->user();

        // `can()` (not hasAnyPermission) so a permission missing from the catalogue reads as "no".
        return $user !== null && array_any(self::ALLOWED_PERMISSIONS, static fn (string $permission): bool => $user->can($permission));
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'integer', 'exists:products,id', 'required_without:category_id', 'prohibits:category_id'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id', 'required_without:product_id'],
            'product_typology_id' => ['nullable', 'integer', 'exists:product_typologies,id'],
            'supplier_ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_SUPPLIERS],
            'supplier_ids.*' => ['required', 'integer', 'distinct', Rule::exists('registries', 'id')->where('is_supplier', true)],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function supplierIds(): array
    {
        return array_map('intval', $this->validated('supplier_ids'));
    }

    public function productId(): ?int
    {
        $id = $this->validated('product_id');

        return $id === null ? null : (int) $id;
    }

    public function categoryId(): ?int
    {
        $id = $this->validated('category_id');

        return $id === null ? null : (int) $id;
    }

    public function productTypologyId(): ?int
    {
        $id = $this->validated('product_typology_id');

        return $id === null ? null : (int) $id;
    }
}
