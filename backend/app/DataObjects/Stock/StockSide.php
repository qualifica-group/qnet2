<?php

namespace App\DataObjects\Stock;

/**
 * One side of a stock movement (spec 0307, D-11): the (site, user) pair
 * identifying a balance row; a null user is the free stock of the site.
 */
final readonly class StockSide
{
    public function __construct(
        public int $siteId,
        public ?int $userId = null,
    ) {}
}
