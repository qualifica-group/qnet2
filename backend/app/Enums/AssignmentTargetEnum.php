<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which record an assignment notification is about (spec 0081). Internal
 * only: it never crosses the API boundary, so — unlike the UI-exposed enums
 * of this namespace — it carries no Label/Icon/Color metadata.
 *
 * `Opportunity` covers BOTH the opportunities module and request management:
 * they are the same `App\Models\Opportunity` record seen through two modules
 * with two permission sets (spec 0049, D-1), which is exactly why the deep
 * link for this case is resolved per recipient (RecordLinkResolver) instead
 * of being fixed here.
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
