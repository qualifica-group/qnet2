<?php

declare(strict_types=1);

namespace App\WorkOrders;

use App\Models\ProductSupplier;
use App\Models\WorkOrder;
use App\Products\ProductSupplierRule;

/**
 * Supplier of a commessa cost row (spec 0320, D-9): a product WITH suppliers
 * requires one of them; a product WITHOUT suppliers (or no product) leaves the
 * supplier free (`is_supplier` registry), unlike offer lines. Only rows that
 * are NEW or whose product/supplier differ from the saved cost are checked,
 * so a historic cost stays valid when the product's supplier list changes.
 */
final class WorkOrderCostSupplierRule
{
    /**
     * @param  array<int, mixed>  $rows  the submitted `lines`
     * @return array<int, string> i18n key suffix (`supplier_required`|`supplier_not_of_product`) per row index
     */
    public static function violations(WorkOrder $workOrder, array $rows): array
    {
        $saved = $workOrder->costs()->get(['id', 'product_id', 'supplier_id'])->keyBy('id');
        $pairs = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row) || ! isset($row['product_id']) || ! is_numeric($row['product_id'])) {
                continue;
            }

            $productId = (int) $row['product_id'];
            $persisted = isset($row['id']) && is_numeric($row['id']) ? $saved->get((int) $row['id']) : null;
            $sameProduct = $persisted !== null && (int) $persisted->product_id === $productId;
            $submitted = is_numeric($row['supplier_id'] ?? null) ? (int) $row['supplier_id'] : null;
            $supplierId = array_key_exists('supplier_id', $row) || ! $sameProduct
                ? $submitted
                : ($persisted->supplier_id === null ? null : (int) $persisted->supplier_id);

            if ($sameProduct && $supplierId === ($persisted->supplier_id === null ? null : (int) $persisted->supplier_id)) {
                continue;
            }

            $pairs[(int) $index] = ['product_id' => $productId, 'supplier_id' => $supplierId];
        }

        $withSuppliers = ProductSupplier::query()
            ->whereIn('product_id', array_column($pairs, 'product_id'))
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $pairs = array_filter($pairs, static fn (array $pair): bool => in_array($pair['product_id'], $withSuppliers, true));

        return array_map(
            static fn (string $violation): string => "supplier_{$violation}",
            ProductSupplierRule::violations($pairs),
        );
    }
}
