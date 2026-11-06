<?php

namespace App\Enums;

/**
 * Lifecycle of an OutboundEmail (spec 0175, D-2): `Draft` -> `Queued` ->
 * `Sent` | `Failed`. A draft is edited/deleted only by its own author while
 * in this state; a `Failed` email may be resent (back to `Queued`, D-2);
 * `Sent` is immutable.
 *
 * Spec 0362, D-6/D-7: a draft can be programmed (`Scheduled`, never sent
 * itself: each occurrence is a COPY), and the system ends the series
 * (`ScheduleEnded`); both go back to `Draft` through unschedule.
 */
enum OutboundEmailStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
    case Scheduled = 'scheduled';
    case ScheduleEnded = 'schedule_ended';
}
