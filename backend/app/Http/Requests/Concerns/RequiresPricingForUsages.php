<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ProductUsage;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;

/**
 * Spec 0191, D-1/D-3: a product's price is required only while it is
 * Sellable and its cost only while it is Usable as cost. Kept in ONE place so
 * Store and Update cannot drift apart.
 *
 * The rule runs on EFFECTIVE values, not on the raw payload: a partial PATCH
 * that adds a usage to a product whose matching value is still empty must
 * fail, and one that drops a usage must not wipe or revalidate the value it
 * no longer needs (D-2). Hence the base rules stay `nullable|numeric` and the
 * "required" part lives here.
 */
trait RequiresPricingForUsages
{
    /** Usage that makes each money field mandatory. */
    private const array PRICING_FIELD_USAGES = [
        'price' => ProductUsage::Sale,
        'cost' => ProductUsage::Cost,
    ];

    protected function enforcePricingForUsages(Validator $validator): void
    {
        // An invalid `usages` already reports its own error: guessing the
        // effective set from it would only add noise.
        foreach ($validator->errors()->keys() as $key) {
            if ($key === 'usages' || str_starts_with($key, 'usages.')) {
                return;
            }
        }

        $usages = $this->effectiveUsages();
        $persisted = $this->persistedProduct();

        foreach (self::PRICING_FIELD_USAGES as $field => $usage) {
            if (! in_array($usage, $usages, true)) {
                continue;
            }

            $value = $this->has($field) ? $this->input($field) : $persisted?->{$field};

            if ($value === null) {
                $validator->errors()->add($field, trans('validation.required', ['attribute' => str_replace('_', ' ', $field)]));
            }
        }
    }

    /**
     * Submitted `usages`, else the persisted ones, else the model default
     * (Sellable only) when creating.
     *
     * @return array<int, ProductUsage>
     */
    private function effectiveUsages(): array
    {
        if ($this->has('usages')) {
            return array_values(array_filter(array_map(
                static fn (mixed $usage): ?ProductUsage => is_string($usage) ? ProductUsage::tryFrom($usage) : null,
                (array) $this->input('usages'),
            )));
        }

        $persisted = $this->persistedProduct();

        return $persisted !== null
            ? ($persisted->usages?->all() ?? [])
            : [ProductUsage::Sale];
    }

    /** The product being updated; null on create. */
    private function persistedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
