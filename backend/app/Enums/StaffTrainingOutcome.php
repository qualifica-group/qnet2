<?php

namespace App\Enums;

/**
 * Result of a staff training (spec 0284, D-3).
 */
enum StaffTrainingOutcome: string
{
    case Passed = 'passed';
    case Failed = 'failed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
