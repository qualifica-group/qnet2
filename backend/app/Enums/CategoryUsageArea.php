<?php

namespace App\Enums;

/**
 * Which modules a product-category branch is used in (spec 0389). OWNED BY
 * THE ROOT of each branch and mirrored onto every descendant by
 * CategoryUsageAreaInheritance. `Requests` is Gestione Richieste and Gestione
 * Iscritti (the same records); `Opportunities` is the Opportunita' module,
 * whose offers inherit the opportunity's classification.
 */
enum CategoryUsageArea: string
{
    case All = 'all';
    case Requests = 'requests';
    case Opportunities = 'opportunities';

    /** Whether a category of this area may be picked in the $module area. */
    public function allows(self $module): bool
    {
        return $this === self::All || $this === $module;
    }

    /**
     * The values a category may hold to be listed in the $module area.
     *
     * @return array<int, string>
     */
    public static function valuesVisibleIn(self $module): array
    {
        return array_values(array_map(
            static fn (self $area): string => $area->value,
            array_filter(self::cases(), static fn (self $area): bool => $area->allows($module)),
        ));
    }
}
