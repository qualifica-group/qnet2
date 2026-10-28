<?php

namespace App\Products;

use App\DataObjects\Products\ProductSupplierData;
use App\Models\Product;

/**
 * Replaces a product's supplier list with the submitted one (spec 0320, D-1/D-2).
 * Rows are matched on `supplier_id` so an unchanged supplier keeps its row id;
 * the rows no longer listed are removed. No activity log on the rows: they are
 * a child collection of the Product, like the quote lines of a quote.
 *
 * Spec 0320, D-22: a supplier that carries a Supplier commission on the
 * product is paid through that commission, so its cost is stored as 0 whatever
 * the client sent.
 */
final class ProductSupplierSync
{
    public function __construct(private readonly SupplierCommissionLookup $commissions) {}

    /**
     * @param  array<int, ProductSupplierData>  $suppliers  the complete list, [] removes them all
     */
    public function sync(Product $product, array $suppliers): void
    {
        $keptIds = array_map(static fn (ProductSupplierData $row): int => $row->supplierId, $suppliers);

        $product->productSuppliers()->whereNotIn('supplier_id', $keptIds)->delete();

        $commissions = $this->commissions->forSuppliers($keptIds, $product->id);

        foreach ($suppliers as $row) {
            $product->productSuppliers()->updateOrCreate(
                ['supplier_id' => $row->supplierId],
                ['price' => $row->price, 'cost' => $commissions[$row->supplierId] === null ? $row->cost : '0.00', 'is_default' => $row->isDefault],
            );
        }

        $product->unsetRelation('productSuppliers');
    }
}
