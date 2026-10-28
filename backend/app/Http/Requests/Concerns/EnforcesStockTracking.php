<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;

/**
 * Spec 0307, D-2/D-16: "Internal use" belongs to physical goods only, and a
 * product holding stock cannot stop being tracked. Runs on EFFECTIVE values
 * (the submitted ones over the persisted ones).
 */
trait EnforcesStockTracking
{
    protected function enforceStockTracking(Validator $validator): void
    {
        foreach ($validator->errors()->keys() as $key) {
            if ($key === 'product_type' || $key === 'usages' || str_starts_with($key, 'usages.')) {
                return;
            }
        }

        $persisted = $this->persistedProduct();
        $type = $this->has('product_type')
            ? ProductType::tryFrom((string) $this->input('product_type'))
            : ($persisted?->product_type ?? ProductType::default());
        $isPhysical = $type === ProductType::Physical;
        $hasInternal = in_array(ProductUsage::Internal, $this->effectiveUsages(), true);

        if ($hasInternal && ! $isPhysical) {
            $validator->errors()->add('usages', __('stock.internal_requires_physical'));
        }

        if ($persisted === null || ! $persisted->isStockTracked() || ($hasInternal && $isPhysical)) {
            return;
        }

        if ($persisted->stockBalances()->exists()) {
            $validator->errors()->add($isPhysical ? 'usages' : 'product_type', __('stock.product_has_stock'));
        }
    }

    /**
     * Submitted `usages`, else the persisted ones, else the model default
     * (Sellable only) when creating.
     *
     * @return array<int, ProductUsage>
     */
    protected function effectiveUsages(): array
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
    protected function persistedProduct(): ?Product
    {
        $product = $this->route('product');

        return $product instanceof Product ? $product : null;
    }
}
