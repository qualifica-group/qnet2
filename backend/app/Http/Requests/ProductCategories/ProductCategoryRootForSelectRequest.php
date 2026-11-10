<?php

namespace App\Http\Requests\ProductCategories;

/**
 * The root picker query: the branch picker's plus `current_only` (spec 0374),
 * which keeps the non-legacy roots and drops the "Tutte" option, and
 * `hide_linked_legacy` (spec 0374 D-12), which hides the legacy roots linked to
 * a current one (`ids[]` still resolves them).
 */
class ProductCategoryRootForSelectRequest extends ProductCategoryBranchForSelectRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [...parent::rules(), 'current_only' => ['sometimes', 'boolean'], 'hide_linked_legacy' => ['sometimes', 'boolean']];
    }

    public function hideLinkedLegacy(): bool
    {
        return (bool) $this->validated('hide_linked_legacy', false);
    }

    public function currentOnly(): bool
    {
        return (bool) $this->validated('current_only', false);
    }
}
