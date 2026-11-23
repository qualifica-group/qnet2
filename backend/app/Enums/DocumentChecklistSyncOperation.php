<?php

namespace App\Enums;

/**
 * Remote operation performed by the checklist sync job (spec 0403).
 */
enum DocumentChecklistSyncOperation: string
{
    case Upsert = 'upsert';
    case Delete = 'delete';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
