<?php

namespace App\Http\Requests\ProductCategories;

/**
 * The root picker query: the branch picker's plus `current_only` (spec 0374),
 * which keeps the non-legacy roots and drops the "Tutte" option.
 */
class ProductCategoryRootForSelectRequest extends ProductCategoryBranchForSelectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'current_only' => ['sometimes', 'boolean']];
    }

    public function currentOnly(): bool
    {
        return (bool) $this->validated('current_only', false);
    }
}
