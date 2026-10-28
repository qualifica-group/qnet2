<?php

namespace Database\Seeders\Support;

use App\Models\ProductSupplier;

/**
 * The supplier a seeded offer row must carry (spec 0320, D-5/D-6): the
 * product's default one, null when the product has no suppliers.
 */
final class DefaultProductSupplier
{
    public static function idFor(int $productId): ?int
    {
        $supplierId = ProductSupplier::query()
            ->where('product_id', $productId)
            ->where('is_default', true)
            ->value('supplier_id');

        return $supplierId === null ? null : (int) $supplierId;
    }
}
