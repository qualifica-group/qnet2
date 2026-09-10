<?php

declare(strict_types=1);

namespace App\Tables\Leads;

use App\Models\Lead;
use App\Services\Assignment\AssignmentSiteResolver;
use App\Services\Assignment\LeadCompetence;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * The two NON-VISIBLE row keys the Operatore cell editor narrows its picker
 * with (direttiva utente 2026-09-10: the inline dropdown must offer the same
 * operators the import assignment offers, not every user in the system):
 * `assignment_site_id` and `assignment_category_ids`.
 *
 * Both come from the SAME services the three assignment surfaces already use
 * (spec 0113) — AssignmentSiteResolver, i.e. the Sede of the lead's CAMPAIGN
 * (D-3), never `leads.operational_site_id`, and LeadCompetence, i.e. the
 * lead's own "Prodotti di interesse" falling back to its campaign's effective
 * lines — so the picker can never offer a set the assignment itself would
 * refuse to consider.
 *
 * Those services are batch by design, but TableDefinition::mapRow() sees one
 * row at a time. The page is therefore resolved ONCE from the `afterQuery`
 * hook LeadsTableDefinition::baseQuery() registers, which receives the whole
 * hydrated page right before mapRow() walks it. The memo holds exactly the
 * last hydrated batch, so an export streaming chunk after chunk never
 * accumulates.
 *
 * UI narrowing only: the write path (RelationValueScopeChecker) ignores
 * `relation.scope` by design — spec 0110 R-1 keeps a hand-picked
 * non-competent operator acceptable, and this column declares no `lockScope`.
 */
final class LeadAssignmentScope
{
    /** The row key carrying the Sede the picker filters by (see `relation.scope`). */
    public const string SITE_KEY = 'assignment_site_id';

    /** The row key carrying the categories the picker filters competence by. */
    public const string CATEGORIES_KEY = 'assignment_category_ids';

    /** @var array<int, int|null> */
    private array $siteByLead = [];

    /** @var array<int, array<int, int>> */
    private array $categoriesByLead = [];

    public function __construct(
        private readonly AssignmentSiteResolver $siteResolver,
        private readonly LeadCompetence $competence,
    ) {}

    /**
     * Resolve the freshly hydrated page in one batch.
     *
     * Guarded on the Eloquent collection: `afterQuery` callbacks fire for
     * `pluck()` too (a plain value collection), which the derived-column
     * helpers run against this very builder.
     */
    public function warm(mixed $result): void
    {
        if (! $result instanceof EloquentCollection) {
            return;
        }

        /** @var array<int, int> $leadIds */
        $leadIds = array_map(intval(...), $result->modelKeys());

        $this->siteByLead = $this->siteResolver->forLeads($leadIds);
        $this->categoriesByLead = $this->competence->requiredByLead($leadIds);
    }

    /**
     * The two keys for one row of the warmed batch. A row mapped outside a
     * warmed batch answers "no scope": the editor then sends no param and
     * lists everyone, exactly like a row whose scope column is empty.
     *
     * @return array<string, mixed>
     */
    public function project(Lead $lead): array
    {
        return [
            self::SITE_KEY => $this->siteByLead[(int) $lead->id] ?? null,
            self::CATEGORIES_KEY => $this->categoriesByLead[(int) $lead->id] ?? [],
        ];
    }
}
