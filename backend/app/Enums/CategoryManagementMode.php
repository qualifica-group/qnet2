<?php

namespace App\Enums;

/**
 * Card-line policy a product-category branch enforces (spec 0077): "single"
 * allows exactly one Category + Category Product row per card, "multiple"
 * keeps the pre-existing, unconstrained behaviour. OWNED BY THE ROOT of each
 * branch and mirrored onto every descendant by CategoryManagementModeInheritance
 * — same denormalisation as `requires_quote`, never resolved by an implicit
 * walk at read time (D-2). Plain enum (no App\Enums\Concerns\HasMeta): the
 * value is never a standalone client select (`config/config.php` form_enums),
 * it travels with the category tree/for-select payloads instead.
 */
enum CategoryManagementMode: string
{
    case Single = 'single';
    case Multiple = 'multiple';
}
