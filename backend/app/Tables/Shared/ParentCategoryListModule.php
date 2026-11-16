<?php

declare(strict_types=1);

namespace App\Tables\Shared;

/**
 * The list pages carrying the "Categoria madre" filter, by permission prefix.
 * The module decides which `viewAll` permission lifts the competence limit of
 * that filter (user directive 2026-11-16, ActorParentCategories).
 */
enum ParentCategoryListModule: string
{
    case WorkOrders = 'work-orders';
    case Contracts = 'contracts';
    case Quotes = 'quotes';
    case Opportunities = 'opportunities';

    public function viewAllPermission(): string
    {
        return "{$this->value}.viewAll";
    }
}
