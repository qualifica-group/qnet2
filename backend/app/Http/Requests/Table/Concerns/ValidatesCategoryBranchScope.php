<?php

declare(strict_types=1);

namespace App\Http\Requests\Table\Concerns;

use Illuminate\Validation\Rule;

/**
 * The OPTIONAL `product_category_branch_id` of the table endpoints that return
 * a config: the root (parent-category) tab the client is on, so the response
 * carries that root's `attr.*` columns (spec 0350 D-4, rev.3). Like
 * `product_category_id`, it never touches what is persisted.
 */
trait ValidatesCategoryBranchScope
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function categoryBranchRules(): array
    {
        return [
            'product_category_branch_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_categories', 'id'), 'prohibits:product_category_id'],
        ];
    }

    public function productCategoryBranchId(): ?int
    {
        $value = $this->validated('product_category_branch_id');

        return $value === null ? null : (int) $value;
    }
}
