<?php

namespace App\Enums;

/**
 * Remote state of a document request (spec 0428, D-5): written only from an
 * ArchivioSmart response or webhook.
 */
enum DocumentRequestStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /**
     * States in which the request can no longer be edited.
     *
     * @return array<int, self>
     */
    public static function closed(): array
    {
        return [self::Completed, self::Expired, self::Cancelled];
    }

    /**
     * States in which the customer can be reminded.
     *
     * @return array<int, self>
     */
    public static function remindable(): array
    {
        return [self::Sent, self::InProgress];
    }
}
