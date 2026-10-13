<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\ProductCategory;
use App\Services\ProductCategories\CategoryActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The category id must exist and be EFFECTIVELY active (spec 0208): its own
 * `is_active` and every ancestor's. No `is_selectable` constraint — the
 * competence rows (spec 0129 D-6) may point at an active container category.
 *
 * REPLACES `exists:product_categories,id` on those fields (one message per
 * field). `$exemptIds` carries spec 0208 D-2: a category already persisted on
 * the record being updated stays valid even once deactivated.
 *
 * A blank value passes: whether the field is required is the caller's call.
 */
final class ActiveProductCategory implements ValidationRule
{
    /** Resolved once per rule instance: a request validates many rows with one rule. */
    private ?CategoryActivity $activity = null;

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

        if (! is_numeric($value) || ! ProductCategory::query()->whereKey((int) $value)->exists()) {
            $fail(__('The selected product category is invalid.'));

            return;
        }

        $categoryId = (int) $value;

        if (! in_array($categoryId, $this->exemptIds, true) && ! $this->activity()->isActive($categoryId)) {
            $fail(__('This product category is not active.'));
        }
    }

    private function activity(): CategoryActivity
    {
        return $this->activity ??= app(CategoryActivity::class);
    }
}
