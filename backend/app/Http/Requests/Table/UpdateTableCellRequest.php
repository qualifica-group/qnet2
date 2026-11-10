<?php

namespace App\Http\Requests\Table;

use App\Http\Requests\Table\Concerns\ValidatesCategoryBranchScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PATCH /api/tables/{domain}/rows/{row} (spec
 * 0053, `note` added by spec 0054 D-5): only the request SHAPE — `column` is
 * a non-empty string, `value` is whatever scalar/null the client sends,
 * `note` an optional string. Whether `column` is actually editable for this
 * actor/row, whether `value` satisfies that column's own derived rules, and
 * whether `note` is accepted on that column at all, are guards
 * TableCellUpdateService enforces against the resolved TableDefinition and
 * the REAL row — never here, mirroring every other Table FormRequest's
 * convention (authorization/whitelisting stay out of the FormRequest so a
 * malformed shape never masks the real 403/422/404).
 *
 * `product_category_id` / `product_category_branch_id` (spec 0382 D-11,
 * optional): the category tab the cell was edited in, as the rows endpoint
 * receives it.
 */
class UpdateTableCellRequest extends FormRequest
{
    use ValidatesCategoryBranchScope;

    public function authorize(): bool
    {
        // Authorization handled in the controller/service against the
        // resolved TableDefinition and the real row.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'column' => ['required', 'string'],
            'value' => ['sometimes'],
            'note' => ['sometimes', 'nullable', 'string'],
            'product_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id')],
            ...$this->categoryBranchRules(),
        ];
    }

    public function productCategoryId(): ?int
    {
        $value = $this->validated('product_category_id');

        return $value === null ? null : (int) $value;
    }
}
