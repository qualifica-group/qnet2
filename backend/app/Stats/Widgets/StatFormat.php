<?php

declare(strict_types=1);

namespace App\Stats\Widgets;

/**
 * How the frontend renders a numeric widget value (spec 0026). `currency` and
 * `percent` values stay raw numbers server-side (0..100 for percent); only the
 * presentation is a frontend concern.
 */
enum StatFormat: string
{
    case Number = 'number';
    case Currency = 'currency';
    case Percent = 'percent';

    /** Whole minutes, rendered as a duration (`7h 30m`, spec 0147). */
    case Duration = 'duration';
}
