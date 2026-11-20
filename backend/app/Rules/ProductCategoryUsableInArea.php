<?php

namespace App\Rules;

use App\Enums\CategoryUsageArea;
use App\Models\ProductCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A NEW link to a product category from a module of $area is refused when the
 * category's branch is reserved to the other area (spec 0389, D-3). The ids
 * already persisted on the record are exempt, so history stays re-savable.
 * Existence and selectability are SelectableProductCategory's job: an id this
 * rule cannot find is left to it.
 */
final class ProductCategoryUsableInArea implements ValidationRule
{
    /**
     * @param  array<int, int>  $exemptIds  ids already persisted on the record being updated
     */
    public function __construct(
        private readonly CategoryUsageArea $area,
        private readonly array $exemptIds = [],
    ) {}

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value) || in_array((int) $value, $this->exemptIds, true)) {
            return;
        }

        /** @var ProductCategory|null $category */
        $category = ProductCategory::query()->find((int) $value, ['id', 'usage_area']);

        if ($category !== null && ! $category->usage_area->allows($this->area)) {
            $fail(__('This product category is not used in this module.'));
        }
    }
}
