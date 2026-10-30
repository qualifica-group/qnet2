<?php

declare(strict_types=1);

namespace App\Tables\CategoryTabs;

use Illuminate\Http\Request;

/**
 * The grids that show the category tab strip next to Gestione Richieste
 * (spec 0335). The grid is resolved from the MATCHED route's default, never
 * from client input.
 */
enum CategoryTabGrid: string
{
    case Opportunities = 'opportunities';
    case Quotes = 'quotes';

    public const string ROUTE_DEFAULT = 'categoryTabGrid';

    /**
     * "{value}.{ability}", the same prefix the grid's policy mints.
     */
    public function permission(string $ability): string
    {
        return "{$this->value}.{$ability}";
    }

    public static function fromRequest(Request $request): self
    {
        return self::from($request->route()?->defaults[self::ROUTE_DEFAULT]);
    }
}
