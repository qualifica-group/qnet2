<?php

namespace App\Enums;

/**
 * Outcome of the last outbound call of a document request (spec 0428, D-4).
 */
enum DocumentRequestDeliveryStatus: string
{
    case Pending = 'pending';
    case Delivered = 'delivered';
    case Failed = 'failed';
}
