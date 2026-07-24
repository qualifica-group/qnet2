<?php

namespace App\Enums;

/**
 * Fractional column span of a placed attribute within its row (spec 0062,
 * layout-contract): resolved against the section's own `columns` grid
 * (full=N, two_thirds=ceil(2N/3), half=ceil(N/2), third=ceil(N/3)) by the
 * frontend renderer via a static Tailwind class map — never a dynamically
 * built class string.
 */
enum LayoutItemWidth: string
{
    case Full = 'full';
    case TwoThirds = 'two_thirds';
    case Half = 'half';
    case Third = 'third';
}
