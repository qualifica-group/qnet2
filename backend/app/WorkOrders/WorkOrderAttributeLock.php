<?php

declare(strict_types=1);

namespace App\WorkOrders;

/**
 * A source that owns some work order attributes (e.g. a client-specific
 * integration writing them from an external system): the user cannot edit
 * them, only its own writer can. Registered by tagging the implementation
 * `work-orders.attribute-locks` in the container.
 */
interface WorkOrderAttributeLock
{
    /**
     * The attribute codes it owns for a work order resolving these product
     * categories (the categories of its quote lines' products).
     *
     * @param  list<int>  $categoryIds
     * @return list<string>
     */
    public function lockedCodes(array $categoryIds): array;
}
