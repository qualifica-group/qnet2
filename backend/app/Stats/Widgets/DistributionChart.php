<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * How the frontend renders a DistributionWidget (spec 0152, D-2). The
 * definition picks the shape once, based on the data's own shape:
 * `Bars` for rankings with long labels, `Columns` for a few short-labelled
 * categories, `Donut` for a part-of-a-whole read (max 6 slices), `Stacked`
 * for a single 100% stacked bar. Beyond 6 items in donut/stacked, the
 * frontend folds the overflow into "Others" — a frontend concern, not this
 * enum's.
 */
enum DistributionChart: string
{
    case Bars = 'bars';
    case Columns = 'columns';
    case Donut = 'donut';
    case Stacked = 'stacked';
}
