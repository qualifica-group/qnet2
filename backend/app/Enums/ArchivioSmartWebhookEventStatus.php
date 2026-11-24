<?php

namespace App\Enums;

/**
 * Processing state of an inbound ArchivioSmart webhook event (spec 0428, D-6).
 */
enum ArchivioSmartWebhookEventStatus: string
{
    case Received = 'received';
    case Processed = 'processed';
    case Ignored = 'ignored';
}
