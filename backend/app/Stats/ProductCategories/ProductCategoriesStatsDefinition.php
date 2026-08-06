<?php

declare(strict_types=1);

namespace App\Stats\ProductCategories;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `product-categories` module (spec 0026): the size
 * and shape of the tree (roots) plus how the catalogue distributes over it.
 */
class ProductCategoriesStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'product_categories';

    private const string PRODUCTS_TABLE = 'products';

    public function domain(): string
    {
        return 'product-categories';
    }

    public function modelClass(): string
    {
        return ProductCategory::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        return [
            $this->stat('total', $this->totalRows(), icon: 'folder-tree'),
            $this->stat(
                key: 'root_categories',
                value: ProductCategory::query()->whereNull('parent_id')->count(),
                icon: 'layers',
            ),
            $this->stat(
                key: 'with_products',
                value: Aggregates::countWithRelated(self::TABLE, self::PRODUCTS_TABLE, 'category_id'),
                icon: 'package',
            ),
            // Inheritance is opted out per usage context (Product / Quote,
            // spec 0084), so a category is a full inheritance ROOT (spec
            // 0025) only when it opts out of BOTH: the counter tracks those
            // still inheriting in at least one context.
            $this->stat(
                key: 'inherits_attributes',
                value: ProductCategory::query()
                    ->where('inherits_product_attributes', true)
                    ->orWhere('inherits_quote_attributes', true)
                    ->count(),
                icon: 'layers',
            ),
            $this->distribution(
                key: 'by_products',
                items: Aggregates::topRelated(
                    query: DB::table(self::PRODUCTS_TABLE),
                    foreignKey: self::PRODUCTS_TABLE.'.category_id',
                    relatedTable: 'product_categories',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                // Denominator: the catalogue, not the categories — each bar is
                // the share of products that sit in that category.
                total: Product::query()->count(),
            ),
        ];
    }
}
