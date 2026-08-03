<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ProductCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The category id must exist AND be a valid classification TARGET (spec 0074):
 * a category flagged `is_selectable = false` exists only as a container in the
 * tree and may not be associated to a product, a product line, a project, a
 * campaign or a commission configuration.
 *
 * REPLACES `exists:product_categories,id` on those fields — it does not sit
 * next to it, or a missing id would raise two messages for the same field.
 *
 * `$exemptIds` carries spec 0074 D-3b: the flag only ever blocks NEW
 * associations, so a partial update that resubmits the value already
 * persisted on the record must still pass, even once that category has been
 * made unselectable.
 *
 * A blank value passes: whether the field is required is the FormRequest's
 * call, mirroring the other rules in this namespace.
 */
final class SelectableProductCategory implements ValidationRule
{
    /**
     * @param  array<int, int>  $exemptIds  ids already persisted on the record being updated
     */
    public function __construct(private readonly array $exemptIds = []) {}

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_numeric($value)) {
            $fail(__('The selected product category is invalid.'));

            return;
        }

        $categoryId = (int) $value;

        /** @var ProductCategory|null $category */
        $category = ProductCategory::query()->find($categoryId, ['id', 'is_selectable']);

        if ($category === null) {
            $fail(__('The selected product category is invalid.'));

            return;
        }

        if (! $category->is_selectable && ! in_array($categoryId, $this->exemptIds, true)) {
            $fail(__('This product category is not selectable.'));
        }
    }
}
