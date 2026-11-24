<?php

namespace App\Enums;

/**
 * Outbound operation of the document request sync job (spec 0428, D-4).
 */
enum DocumentRequestOperation: string
{
    case Upsert = 'upsert';
    case Remind = 'remind';
    case Cancel = 'cancel';
    case Refresh = 'refresh';
}
