<?php

declare(strict_types=1);

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the OPTIONAL `product_category_id` query parameter for
 * `GET /api/tables/{domain}/columns` (spec 0064): generic across every
 * domain (harmless for one that never sends it), but only
 * `AttributeScopedTableDefinition` (`request-management`) actually narrows
 * its response from this value — see `TableController::columns()`.
 *
 * Authorization stays in the controller via the definition's viewAny, same
 * convention as every other Table FormRequest.
 */
class TableColumnsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
        ];
    }

    public function productCategoryId(): ?int
    {
        $value = $this->validated('product_category_id');

        return $value === null ? null : (int) $value;
    }
}
