<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which record an assignment notification is about (spec 0081). Internal
 * only: it never crosses the API boundary, so — unlike the UI-exposed enums
 * of this namespace — it carries no Label/Icon/Color metadata.
 *
 * `Opportunity` links to the opportunities module only (rev. 2026-10-01,
 * decisione utente): request management opens Offerte, never Opportunities,
 * so a recipient who cannot see opportunities gets no link (RecordLinkResolver).
 *
 * `Quote` (spec 0186) is an Offerta: assigned from its own form or from
 * Gestione Richieste, whose rows ARE Offerte (spec 0086). Its link follows
 * the Offerta's own module fallback, never the parent Opportunity's.
 */
enum AssignmentTargetEnum: string
{
    case Registry = 'REGISTRY';

    case Opportunity = 'OPPORTUNITY';

    case Quote = 'QUOTE';
}
