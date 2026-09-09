<?php

namespace App\DataObjects\Assignment;

/**
 * What a bulk operator assignment actually did (spec 0110): how many records
 * received an operator, and how many were deliberately LEFT WITHOUT one
 * because no candidate was competent for them (AC-021).
 *
 * A declared object rather than a two-key array: the three assignment
 * surfaces (import rows, real leads, Gestione richieste offers) all return
 * it and all three controllers render it into their own response envelope,
 * so the pair must have one shape and one meaning.
 *
 * Records the caller could not reach at all (Gestione richieste' D-3 scope)
 * are in NEITHER counter: they were never in play.
 */
final readonly class AssignmentOutcome
{
    public function __construct(
        public int $assigned,
        public int $skipped = 0,
    ) {}
}
