<?php

namespace App\Enums;

/**
 * State of the preview of a turn (spec 0433, `AiPreview.state`): `open` while the turn waits,
 * then what the click or the clock made of it.
 */
enum AiPreviewState: string
{
    case Open = 'open';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Rejected = 'rejected';
    case Uncertain = 'uncertain';
    case Stale = 'stale';
}
