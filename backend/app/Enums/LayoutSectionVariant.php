<?php

namespace App\Enums;

/**
 * Visual emphasis of a section in an attribute layout blob (spec 0062,
 * layout-contract). Purely presentational — the frontend renderer maps each
 * case to a static Tailwind class set, never a dynamically built string.
 */
enum LayoutSectionVariant: string
{
    case Default = 'default';
    case Highlighted = 'highlighted';
    case Informative = 'informative';
    case Secondary = 'secondary';
}
