<?php

declare(strict_types=1);

namespace App\Services\Stock;

use App\Models\StockBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Reads of the stock for the product and user sections and the row history
 * (spec 0307).
 */
final class StockQueryService
{
    public const int HISTORY_LIMIT = 200;

    /**
     * Ordered by site, free rows before assigned ones, then product.
     *
     * @param  array{product_id?: int, user_id?: int, operational_site_id?: int}  $filters
     * @return Collection<int, StockBalance>
     */
    public function balances(array $filters): Collection
    {
        return StockBalance::query()
            ->with(StockMovementService::BALANCE_RELATIONS)
            ->when(isset($filters['product_id']), fn (Builder $query) => $query->where('product_id', $filters['product_id']))
            ->when(isset($filters['user_id']), fn (Builder $query) => $query->where('user_id', $filters['user_id']))
            ->when(isset($filters['operational_site_id']), fn (Builder $query) => $query->where('operational_site_id', $filters['operational_site_id']))
            ->orderBy('operational_site_id')
            ->orderBy('holder_key')
            ->orderBy('product_id')
            ->get();
    }

    /**
     * The movements where the (site, user) pair of a product is source or destination.
     *
     * @return Collection<int, StockMovement>
     */
    public function history(int $productId, int $siteId, ?int $userId): Collection
    {
        $isHolder = static fn (string $side) => static fn (Builder $query) => $query
            ->where("{$side}_operational_site_id", $siteId)
            ->when(
                $userId === null,
                static fn (Builder $inner) => $inner->whereNull("{$side}_user_id"),
                static fn (Builder $inner) => $inner->where("{$side}_user_id", $userId),
            );

        return StockMovement::query()
            ->with(StockMovementService::MOVEMENT_RELATIONS)
            ->where('product_id', $productId)
            ->where(fn (Builder $query) => $query->where($isHolder('from'))->orWhere($isHolder('to')))
            ->orderByDesc('moved_on')
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get();
    }
}
