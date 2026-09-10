<?php

declare(strict_types=1);

namespace App\Tables\RequestManagement;

use App\Models\Quote;
use App\Services\Assignment\QuoteCompetence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The NON-VISIBLE row key the Operatore (GA2) cell editor narrows its picker
 * with (direttiva utente 2026-09-10, the twin of the Lead grid's
 * LeadAssignmentScope): `assignment_category_ids`.
 *
 * Only ONE key here, unlike the Lead grid: the Sede this domain scopes by is
 * `quotes.operational_site_id` (spec 0113, D-4 — an offer has no campaign, so
 * the Campagna -> Sede chain does not exist), and the row already carries it
 * as the visible `operational_site` column the picker was scoped by since the
 * direttiva utente 2026-07-23. What was missing is the competence half: the
 * categories the offer's `opportunity_product_lines` require, read from the
 * SAME service the three assignment surfaces use (App\Services\Assignment\
 * QuoteCompetence, spec 0110), so the inline picker can never offer a set the
 * assignment itself would refuse to consider.
 *
 * That service is batch by design, but TableDefinition::mapRow() sees one row
 * at a time. The page is therefore resolved ONCE from the `afterQuery` hook
 * RequestManagementTableDefinition::baseQuery() registers, which receives the
 * whole hydrated page right before mapRow() walks it. The memo holds exactly
 * the last hydrated batch, so an export streaming chunk after chunk never
 * accumulates.
 *
 * This key narrows the PICKER, nothing else: the generic write path
 * (RelationValueScopeChecker) ignores `relation.scope` by design, and
 * `lockScope` — which this column does not declare — is a UI flag, never a
 * gate, and one the single-value editor does not even read (the registry
 * forwards it to the `multiselect` editor alone). What DOES refuse an ineligible operator on this domain is the domain
 * writer, RequestAttributionWriter::assertOperatorCovers() (direttiva utente
 * 2026-09-10), against the very same composition of Sede and competence. The
 * spec 0110 R-1 escape ("a hand-picked non-competent operator is accepted")
 * still holds on the LEAD grid, whose twin LeadAssignmentScope has no such
 * writer guard behind it — do not read the two docblocks as one rule.
 */
final class RequestAssignmentScope
{
    /** The row key carrying the categories the picker filters competence by. */
    public const string CATEGORIES_KEY = 'assignment_category_ids';

    /** @var array<int, array<int, int>> */
    private array $categoriesByQuote = [];

    public function __construct(private readonly QuoteCompetence $competence) {}

    /**
     * Resolve the freshly hydrated page in one batch.
     *
     * Guarded on the Eloquent collection: `afterQuery` callbacks fire for
     * `pluck()` too (a plain value collection), which the distinct-value
     * helpers run against this very builder.
     */
    public function warm(mixed $result): void
    {
        if (! $result instanceof EloquentCollection) {
            return;
        }

        /** @var array<int, int> $quoteIds */
        $quoteIds = array_map(intval(...), $result->modelKeys());

        $this->categoriesByQuote = $this->competence->requiredByQuote($quoteIds);
    }

    /**
     * The key for one row of the warmed batch. A row mapped outside a warmed
     * batch answers "no scope": the editor then sends no param and filters by
     * the Sede alone, exactly like an offer that requires no competence.
     *
     * @return array<string, mixed>
     */
    public function project(Quote $quote): array
    {
        return [self::CATEGORIES_KEY => $this->categoriesByQuote[(int) $quote->id] ?? []];
    }
}
