<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\User;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\OpportunityService;
use Illuminate\Validation\ValidationException;

/**
 * Contextual Lead -> Opportunity conversion (spec 0044): given a just-created
 * Lead, derives and persists its linked Opportunity. Called by
 * LeadService::create() from WITHIN the same DB::transaction it already
 * opened around the Lead insert, so a failure here (e.g. AC-012, empty
 * product lines) rolls the Lead back too — this class does not open its own
 * outer transaction.
 *
 * Reuses LeadOpportunityDefaultsResolver (the single BR-1 derivation point,
 * spec 0040) for registry_id/source_id/product lines, and
 * OpportunityService::create() for the actual persistence (product lines
 * sync, and — spec 0057, D-5 — the `OPP_{id}` name derivation), rather than
 * re-implementing either.
 */
final class ConvertLeadToOpportunity
{
    public function __construct(
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly OpportunityService $opportunityService,
    ) {}

    /**
     * @param  ?User  $actor  who triggered the conversion, propagated to
     *                        OpportunityService so the assignment notifications
     *                        (spec 0081) can exclude them and name them as the
     *                        author. Null on system-initiated conversions (the
     *                        lead import), where there is nobody to exclude.
     */
    public function handle(Lead $lead, ?User $actor = null): Opportunity
    {
        // Step 1: derive the BR-1 values (registry_id/source_id) and the
        // campaign/project's product line from the lead.
        $defaults = $this->defaultsResolver->resolve($lead);

        // Step 2: a campaign/project with no business function or product
        // category derives nothing to seed the opportunity's mandatory
        // product line with (AC-012) — reject before persisting anything.
        if ($defaults->productLines === []) {
            throw ValidationException::withMessages([
                'product_lines' => ["The lead's campaign has no business function or product category to derive an opportunity from."],
            ]);
        }

        // Step 3: persist through OpportunityService::create(), so product
        // line sync (and the `OPP_{id}` name derivation, spec 0057 D-5) stay
        // the single implementation.
        return $this->opportunityService->create(new CreateOpportunityData(
            registryId: $defaults->values['registry_id'],
            referentId: null,
            commercialId: null,
            reporterId: null,
            // User directive 2026-07-21: the lead's Operator no longer becomes
            // the Supervisor — it seeds a "Gestore Account" slot below, and the
            // Supervisor is left empty.
            supervisorId: null,
            sourceId: $defaults->values['source_id'],
            leadId: $lead->id,
            // User directive 2026-07-22: the Operator becomes G.A. 2; G.A. 1 is
            // still materialized, but empty (a null leading slot, gap-aware).
            managerSlots: $lead->operator_id === null ? null : [null, $lead->operator_id],
            productLines: $this->toProductLineAttributes($defaults->productLines),
            startDate: null,
            estimatedValue: null,
            expectedCloseDate: null,
            successProbability: null,
            // User directive 2026-07-23: the opportunity inherits the lead's
            // Sede operativa (plain default, never BR-2-locked).
            operationalSiteId: $defaults->values['operational_site_id'],
            // User directive 2026-07-27: the "Note generali" are seeded from
            // the lead's own notes (plain default, never BR-2-locked).
            generalNotes: $defaults->values['general_notes'],
        ), $actor);
    }

    /**
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function toProductLineAttributes(array $productLines): array
    {
        return array_map(static fn (array $line): array => [
            'business_function_id' => $line['business_function']['id'],
            'product_category_id' => $line['product_category']['id'],
        ], $productLines);
    }
}
