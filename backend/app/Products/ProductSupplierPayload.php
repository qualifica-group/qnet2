<?php

namespace App\Products;

use App\DataObjects\Commissions\ResolvedCommissionRule;
use App\Models\Product;
use App\Models\ProductSupplier;
use Illuminate\Support\Collection;

/**
 * The `suppliers` shape of a product (spec 0320): default first, then by
 * name. Reads the eager-loaded `productSuppliers.supplier`.
 *
 * Two flavours on purpose: the for-select list (hot path, one call per
 * picker row) never resolves commissions; only the detail does, with one
 * lookup per product (spec 0320, D-22).
 */
final class ProductSupplierPayload
{
    /**
     * @return array<int, array{supplier_id: int, name: string, price: string, cost: string, is_default: bool}>
     */
    public static function forSelect(Product $product): array
    {
        return self::rows($product)->all();
    }

    /**
     * @return array<int, array{supplier_id: int, name: string, price: string, cost: string, is_default: bool, commission: array{configuration_id: int, type: string, value: string, direction: string|null}|null}>
     */
    public static function forDetail(Product $product): array
    {
        $rows = self::rows($product);
        $commissions = app(SupplierCommissionLookup::class)->forSuppliers(
            $rows->pluck('supplier_id')->all(),
            $product->id,
        );

        return $rows->map(static fn (array $row): array => [
            ...$row,
            'commission' => self::commission($commissions[$row['supplier_id']] ?? null),
        ])->all();
    }

    /**
     * The commission shape shared with GET /api/products/supplier-commissions.
     *
     * @return array{configuration_id: int, type: string, value: string, direction: string|null}|null
     */
    public static function commission(?ResolvedCommissionRule $rule): ?array
    {
        return $rule === null ? null : [
            'configuration_id' => $rule->configurationId,
            'type' => $rule->type->value,
            'value' => $rule->value,
            'direction' => $rule->supplierDirection?->value,
        ];
    }

    /**
     * @return Collection<int, array{supplier_id: int, name: string, price: string, cost: string, is_default: bool}>
     */
    private static function rows(Product $product): Collection
    {
        return $product->productSuppliers
            ->map(static fn (ProductSupplier $row): array => [
                'supplier_id' => $row->supplier_id,
                'name' => (string) $row->supplier?->name,
                'price' => (string) $row->price,
                'cost' => (string) $row->cost,
                'is_default' => $row->is_default,
            ])
            ->sortBy([['is_default', 'desc'], ['name', 'asc']])
            ->values();
    }
}
