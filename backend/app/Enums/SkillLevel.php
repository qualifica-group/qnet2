<?php

namespace App\Enums;

/**
 * Fixed skill scale (spec 0286 D-3). Labels are translated by the frontend.
 */
enum SkillLevel: int
{
    case Basic = 1;
    case Intermediate = 2;
    case Advanced = 3;
    case Expert = 4;

    /**
     * @return array<int, int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
