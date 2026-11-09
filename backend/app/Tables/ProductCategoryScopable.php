<?php

declare(strict_types=1);

namespace App\Tables;

/**
 * A table definition whose rows can be narrowed to one product category, for
 * the category tab strip of Gestione Richieste and Gestione Iscritti (spec
 * 0064, 0130). Implemented by the outermost row-scope decorator of each
 * supported domain; callers test `instanceof ProductCategoryScopable`, never
 * a concrete class.
 */
interface ProductCategoryScopable
{
    /**
     * Null = no scope (the "Tutte" tab).
     */
    public function scopeToProductCategory(?int $productCategoryId): void;

    /**
     * The rows of one root category's whole branch (the root and every
     * descendant, spec 0337, D-1), with the root's own `attr.*` columns (spec
     * 0350 D-4, rev.3); null = no scope. Mutually exclusive with
     * `scopeToProductCategory`: the callers never set both.
     */
    public function scopeToProductCategoryBranch(?int $rootId): void;
}
