<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * How the frontend renders a TrendWidget's series (spec 0152, D-3): the
 * current `Area` fill, monthly `Columns`, or a 2px `Line` with points.
 */
enum TrendChart: string
{
    case Area = 'area';
    case Columns = 'columns';
    case Line = 'line';
}
