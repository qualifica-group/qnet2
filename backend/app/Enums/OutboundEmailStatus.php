<?php

namespace App\Enums;

/**
 * Lifecycle of an OutboundEmail (spec 0175, D-2): `Draft` -> `Queued` ->
 * `Sent` | `Failed`. A draft is edited/deleted only by its own author while
 * in this state; a `Failed` email may be resent (back to `Queued`, D-2);
 * `Sent` is immutable.
 */
enum OutboundEmailStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sent = 'sent';
    case Failed = 'failed';
}
