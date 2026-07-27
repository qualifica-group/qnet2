<?php

namespace App\Http\Requests\ProductCategories;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates POST /api/product-categories/bulk-move (spec 0063): move many
 * categories under one new parent, or to the root (`parent_id: null`).
 *
 * `parent_id` is `present` rather than `required` — null is a legitimate
 * VALUE here (move to root), which `required` would reject.
 *
 * Authorization is intentionally NOT handled here: it stays in the
 * controller, per targeted category, via ProductCategoryPolicy — same
 * convention as StoreProductCategoryRequest/UpdateProductCategoryRequest.
 */
class BulkMoveCategoriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via ProductCategoryPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'category_ids' => ['required', 'array', 'min:1'],
            'category_ids.*' => ['integer', Rule::exists('product_categories', 'id')],
            'parent_id' => ['present', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    /**
     * The submitted category ids, deduplicated.
     *
     * @return array<int, int>
     */
    public function categoryIds(): array
    {
        /** @var array<int, int|string> $ids */
        $ids = $this->validated('category_ids', []);

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /** The destination parent, or null to move the selection to the root. */
    public function parentId(): ?int
    {
        $parentId = $this->validated('parent_id');

        return $parentId === null ? null : (int) $parentId;
    }
}
