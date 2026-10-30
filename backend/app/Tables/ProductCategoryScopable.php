<?php

declare(strict_types=1);

namespace App\Tables;

/**
 * A table definition whose rows can be narrowed to one product category, for
 * the category tab strip (spec 0064 for Gestione Richieste, spec 0335 for
 * `opportunities` and `quotes`). Implemented by the outermost row-scope
 * decorator of each supported domain; callers test
 * `instanceof ProductCategoryScopable`, never a concrete class.
 */
interface ProductCategoryScopable
{
    /**
     * Null = no scope (the "Tutte" tab).
     */
    public function scopeToProductCategory(?int $productCategoryId): void;
}
