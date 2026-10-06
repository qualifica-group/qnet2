<?php

namespace App\Enums;

/**
 * What an OutboundEmail is for (spec 0195, D-11): NULL on the pre-existing
 * work order emails, `Document` for an invoice's PDF mail, `Reminder` for a
 * payment reminder (feeds the invoices `last_reminder_at` column).
 */
enum OutboundEmailPurpose: string
{
    case Document = 'document';
    case Reminder = 'reminder';
}
