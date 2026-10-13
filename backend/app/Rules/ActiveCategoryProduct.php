<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Product;
use App\Services\ProductCategories\CategoryActivity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * The product id must exist and its category must be EFFECTIVELY active
 * (spec 0208 D-4/D-9): a product of an inactive branch cannot be linked to a
 * NEW record.
 *
 * REPLACES `Rule::exists('products', 'id')` on those fields (one message per
 * field). `$exemptProductIds` carries spec 0208 D-2: products already
 * persisted on the record being updated stay valid.
 *
 * A blank value passes: whether the field is required is the caller's call.
 */
final class ActiveCategoryProduct implements ValidationRule
{
    /** Resolved once per rule instance: a request validates many rows with one rule. */
    private ?CategoryActivity $activity = null;

    /**
     * @param  array<int, int>  $exemptIds  product ids already persisted on the record being updated
     */
    public function __construct(private readonly array $exemptIds = []) {}

    /**
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $productId = is_numeric($value) ? (int) $value : 0;

        /** @var Product|null $product */
        $product = $productId > 0 ? Product::query()->find($productId, ['id', 'category_id']) : null;

        if ($product === null) {
            $fail('validation.exists')->translate();

            return;
        }

        if (in_array($productId, $this->exemptIds, true)) {
            return;
        }

        if ($product->category_id !== null && ! $this->activity()->isActive((int) $product->category_id)) {
            $fail(__('This product belongs to an inactive category.'));
        }
    }

    private function activity(): CategoryActivity
    {
        return $this->activity ??= app(CategoryActivity::class);
    }
}
