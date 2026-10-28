<?php

declare(strict_types=1);

namespace App\Products;

use App\Models\ProductSupplier;

/**
 * The "supplier of the product" rule (spec 0320, D-5/D-9), ONE definition for
 * every row that names a product AND a supplier (quote lines, work order
 * costs): a product WITH suppliers requires one of them; a product WITHOUT
 * suppliers admits none. Generic on purpose: it knows nothing about offers —
 * callers decide WHICH rows to check (only new/changed ones) and map the two
 * outcomes to their own i18n keys.
 *
 * Batch API: one query for all the products involved, whatever the row count.
 */
final class ProductSupplierRule
{
    public const string REQUIRED = 'required';

    public const string NOT_OF_PRODUCT = 'not_of_product';

    /**
     * @param  array<int|string, array{product_id: int, supplier_id: int|null}>  $pairs  rows to check, keyed by the caller's own key
     * @return array<int|string, string> self::REQUIRED|self::NOT_OF_PRODUCT per offending key; valid rows are absent
     */
    public static function violations(array $pairs): array
    {
        if ($pairs === []) {
            return [];
        }

        $productIds = array_values(array_unique(array_column($pairs, 'product_id')));
        $suppliersByProduct = [];

        foreach (ProductSupplier::query()->whereIn('product_id', $productIds)->get(['product_id', 'supplier_id']) as $row) {
            $suppliersByProduct[$row->product_id][] = $row->supplier_id;
        }

        $violations = [];

        foreach ($pairs as $key => $pair) {
            $violation = self::violation($suppliersByProduct[$pair['product_id']] ?? [], $pair['supplier_id']);

            if ($violation !== null) {
                $violations[$key] = $violation;
            }
        }

        return $violations;
    }

    /**
     * @return string|null self::REQUIRED|self::NOT_OF_PRODUCT, null when valid
     */
    public static function check(int $productId, ?int $supplierId): ?string
    {
        return self::violations([['product_id' => $productId, 'supplier_id' => $supplierId]])[0] ?? null;
    }

    /** @param array<int, int> $productSupplierIds */
    private static function violation(array $productSupplierIds, ?int $supplierId): ?string
    {
        if ($productSupplierIds === []) {
            return $supplierId === null ? null : self::NOT_OF_PRODUCT;
        }

        if ($supplierId === null) {
            return self::REQUIRED;
        }

        return in_array($supplierId, $productSupplierIds, true) ? null : self::NOT_OF_PRODUCT;
    }
}
