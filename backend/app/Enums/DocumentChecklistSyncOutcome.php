<?php

namespace App\Enums;

/**
 * Result of one ArchivioSmart call attempt, as logged (spec 0403).
 */
enum DocumentChecklistSyncOutcome: string
{
    case Success = 'success';
    case Failed = 'failed';
    case Retrying = 'retrying';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
