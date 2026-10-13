<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The category id must exist, be EFFECTIVELY active (spec 0208: its own
 * `is_active` and every ancestor's) AND be a valid classification TARGET (spec 0074):
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
 * made unselectable (or, spec 0208 D-2, inactive).
 *
 * Check order: exists -> effectively active -> selectable, stopping at the
 * first failure so a field raises ONE message (spec 0208 AC-015).
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

    /** Resolved once per rule instance: one request validates many rows with one rule. */
    private ?CategoryActivity $activity = null;

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

        $exempt = in_array($categoryId, $this->exemptIds, true);

        if (! $exempt && ! $this->activity()->isActive($categoryId)) {
            $fail(__('This product category is not active.'));

            return;
        }

        if (! $category->is_selectable && ! $exempt) {
            $fail(__('This product category is not selectable.'));
        }
    }

    private function activity(): CategoryActivity
    {
        return $this->activity ??= app(CategoryActivity::class);
    }
}
