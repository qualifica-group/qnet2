<?php

namespace App\Tables\Products;

use App\Models\Product;
use App\Models\User;
use App\Policies\StockPolicy;
use Illuminate\Database\Eloquent\Builder;

/**
 * The `stock_quantity` column of the products grid (spec 0307, D-18): the sum
 * of the product's stock rows, sortable, not filterable. Empty for a product
 * that is not tracked and for an actor who cannot read the stock. The sum is
 * loaded with the rows (withSum) so no row costs a query.
 */
final class ProductStockColumn
{
    public const string COLUMN_ID = 'stock_quantity';

    /**
     * @return array<string, mixed>
     */
    public static function declaration(): array
    {
        return [
            'id' => self::COLUMN_ID,
            'label' => 'products.columns.stock_quantity',
            'type' => 'number',
            'visible' => true,
            'sortable' => true,
            'filterable' => false,
        ];
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function withSum(Builder $query): void
    {
        $query->withSum('stockBalances as '.self::COLUMN_ID, 'quantity');
    }

    /**
     * @param  Builder<Product>  $query
     */
    public static function applySort(Builder $query, string $direction): void
    {
        $query->orderBy(self::COLUMN_ID, $direction);
    }

    public static function value(User $actor, Product $product): ?string
    {
        if (! $product->isStockTracked() || ! app(StockPolicy::class)->viewAny($actor)) {
            return null;
        }

        return bcadd((string) ($product->getAttribute(self::COLUMN_ID) ?? '0'), '0', 3);
    }
}
