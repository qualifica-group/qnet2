<?php

declare(strict_types=1);

namespace App\Exceptions\Leads;

use RuntimeException;

/**
 * A bulk lead -> opportunity conversion (spec 0071) refused BEFORE any write:
 * decision D-1 is all-or-nothing, so a single non-convertible lead rejects the
 * whole batch and no lead is ever left half-converted.
 *
 * Carries every offending lead (not just the first one found) so the
 * controller can surface them in one 422 and the confirm dialog can name them;
 * the exception itself never decides the HTTP status. Mirrors
 * BulkMoveConflictException (spec 0063), the same shape one layer up.
 */
final class BulkConversionBlockedException extends RuntimeException
{
    /** Machine-readable discriminator of the 422 body's `errors.reason`. */
    public const string REASON = 'not_convertible';

    /** The lead already owns an Opportunity. */
    public const string BLOCKER_ALREADY_CONVERTED = 'already_converted';

    /** The lead's campaign (or its project) derives no product line to seed the Opportunity with. */
    public const string BLOCKER_NOT_DERIVABLE = 'not_derivable';

    /** The lead's anagrafica already owns an open Opportunity (user directive 2026-08-31). */
    public const string BLOCKER_REGISTRY_HAS_OPEN_OPPORTUNITY = 'registry_has_open_opportunity';

    /**
     * @param  array<int, array{id: int, reason: string}>  $blockers
     */
    public function __construct(
        public readonly array $blockers,
        string $message,
    ) {
        parent::__construct($message);
    }
}
