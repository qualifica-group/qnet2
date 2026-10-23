<?php

namespace App\Http\Requests\Concerns;

use App\Enums\ProductType;
use App\Enums\ProductUsage;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;

/**
 * Spec 0307, D-2/D-16: "Internal use" belongs to physical goods only, and a
 * product holding stock cannot stop being tracked. Runs on EFFECTIVE values
 * (the submitted ones over the persisted ones), like RequiresPricingForUsages,
 * whose effectiveUsages()/persistedProduct() it relies on.
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
}
