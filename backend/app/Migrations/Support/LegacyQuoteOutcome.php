<?php

declare(strict_types=1);

namespace App\Migrations\Support;

/**
 * The outcome vocabulary of a legacy offer (spec 0189 G-6/G-18), decided by
 * the legacy exporter: qnet only picks the status inside the workflow the
 * offer belongs to.
 */
enum LegacyQuoteOutcome: string
{
    /** Stays on the status it was created with. */
    case Open = 'open';
    case Negotiation = 'negotiation';
    case CallBack = 'call_back';
    case StandBy = 'stand_by';
    case AcceptedByClient = 'accepted_by_client';
    case Won = 'won';
    case Lost = 'lost';
}
