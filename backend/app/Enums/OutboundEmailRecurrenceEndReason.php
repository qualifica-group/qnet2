<?php

namespace App\Enums;

/**
 * Why the system ended a recurring email series (spec 0362, D-7).
 */
enum OutboundEmailRecurrenceEndReason: string
{
    case EndsOnReached = 'ends_on_reached';
    case OwnerClosed = 'owner_closed';
    case SenderUnavailable = 'sender_unavailable';
}
