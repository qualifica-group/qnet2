<?php

namespace App\Enums;

/**
 * Standing of a person for a course (spec 0284, D-4), derived from the latest
 * passed training; never stored.
 */
enum StaffTrainingStatus: string
{
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case Missing = 'missing';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
