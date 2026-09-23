<?php

declare(strict_types=1);

namespace App\Stats\Products;

use App\Enums\ProductType;
use App\Models\Product;
use App\Stats\AbstractStatsDefinition;
use App\Stats\Support\Aggregates;
use App\Stats\Widgets\DistributionChart;
use App\Stats\Widgets\StatFormat;
use App\Stats\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/**
 * Statistics panel of the `products` module (spec 0026): catalogue size, the
 * average price, the average cost and the average margin (price - cost), plus
 * the type and category breakdowns.
 */
class ProductsStatsDefinition extends AbstractStatsDefinition
{
    private const string TABLE = 'products';

    /** AVG ignores NULL rows, so a catalogue with no priced product yields NULL, not 0. */
    private const string AVERAGES_SELECT = 'AVG(price) as average_price, AVG(cost) as average_cost, AVG(price - cost) as average_margin';

    public function domain(): string
    {
        return 'products';
    }

    public function modelClass(): string
    {
        return Product::class;
    }

    /**
     * @return array<int, Widget>
     */
    public function widgets(): array
    {
        $total = $this->totalRows();
        $averages = DB::table(self::TABLE)->selectRaw(self::AVERAGES_SELECT)->first();

        return [
            $this->stat('total', $total, icon: 'package'),
            $this->stat(
                key: 'average_price',
                value: $this->money($averages?->average_price),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            $this->stat(
                key: 'average_cost',
                value: $this->money($averages?->average_cost),
                format: StatFormat::Currency,
                icon: 'wallet',
            ),
            $this->stat(
                key: 'average_margin',
                value: $this->money($averages?->average_margin),
                format: StatFormat::Currency,
                icon: 'trending-up',
            ),
            $this->distribution(
                key: 'by_type',
                items: Aggregates::byEnumColumn(self::TABLE, 'product_type', ProductType::class),
                total: $total,
                chart: DistributionChart::Donut,
            ),
            $this->distribution(
                key: 'by_category',
                items: Aggregates::topRelated(
                    query: DB::table(self::TABLE),
                    foreignKey: self::TABLE.'.category_id',
                    relatedTable: 'product_categories',
                    labelColumn: 'name',
                    limit: self::TOP_LIMIT,
                ),
                total: $total,
                chart: DistributionChart::Bars,
            ),
        ];
    }

    /**
     * A currency KPI stays a raw number (the frontend formats it); NULL when
     * the aggregate has no priced row to average.
     */
    private function money(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
    }
}
