<?php

namespace App\Enums;

/**
 * Sync state of a document checklist towards ArchivioSmart (spec 0403).
 */
enum DocumentChecklistSyncStatus: string
{
    case Pending = 'pending';
    case Synced = 'synced';
    case Failed = 'failed';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
