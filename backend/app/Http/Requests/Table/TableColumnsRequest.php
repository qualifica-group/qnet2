<?php

declare(strict_types=1);

namespace App\Http\Requests\Table;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the OPTIONAL `product_category_id`/`opportunity_id` query
 * parameters for `GET /api/tables/{domain}/columns`: generic across every
 * domain (harmless for one that never sends it), but only
 * `RequestManagementScopedTableDefinition` (`request-management`, spec 0064)
 * and `OpportunityScopedTableDefinition` (`quotes`, spec 0067) actually
 * narrow their response from these values — see `TableController::columns()`.
 * The response SHAPE never changes either way (spec 0067 D-1/AC-009), EXCEPT
 * `request-management`'s `attr.*` columns, which are shape-dependent on the
 * category scope (user directive 2026-08-31).
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
            'opportunity_id' => ['sometimes', 'nullable', 'integer', Rule::exists('opportunities', 'id')],
        ];
    }

    public function productCategoryId(): ?int
    {
        $value = $this->validated('product_category_id');

        return $value === null ? null : (int) $value;
    }

    public function opportunityId(): ?int
    {
        $value = $this->validated('opportunity_id');

        return $value === null ? null : (int) $value;
    }
}
