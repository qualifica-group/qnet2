<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\DataObjects\Opportunities\LeadOpportunityDefaults;
use App\Models\Campaign;
use App\Models\Lead;
use App\Support\OperationalSiteLabel;
use Illuminate\Database\Eloquent\Model;

/**
 * BR-1 (spec 0040, spec 0041 D-3): resolves the values an Opportunity
 * inherits from a Lead and its Campaign — the SINGLE derivation point
 * consumed both by the `GET /api/leads/{lead}/opportunity-defaults` prefill
 * endpoint and by the OpportunityService/StoreOpportunityRequest/
 * UpdateOpportunityRequest lock enforcement (BR-2), so the two can never
 * drift apart.
 *
 * `registry_id` comes straight off the lead (spec 0041 D-3: `registry_id` is
 * now the LEAD's own, no longer the campaign's); `source_id` derives ONLY
 * from the lead's own source — the campaign-source fallback was removed
 * once Campaign stopped carrying a `source` (the campaign/project modules no
 * longer have a source_id/source at all). `referent_id` is NOT
 * derived (spec 0041 D-3): it stays a plain field, scoped to the chosen
 * registry (BR-4, spec 0040).
 *
 * Amendment rev.3: business function/product category are NO LONGER
 * BR-2-locked scalars — `productLines` carries the campaign's EFFECTIVE pair
 * (read through its linked Project when one exists, else the campaign's own
 * — the exact `project !== null ? project->x : campaign->x` merge
 * CampaignResource already uses for the same 2 columns, spec 0023 BR-2, no
 * second implementation) as a single EDITABLE/removable row, only when BOTH
 * are present.
 *
 * User directive 2026-07-23: the lead's `operational_site_id` (the Sede
 * operativa) is inherited by the opportunity again — but as a PLAIN default,
 * deliberately OUT of `DERIVED_FIELDS`/`lockedFields()` like `state_id`: the
 * conversion prefills it, the user stays free to change or clear it. The
 * 2026-07-17 directive that removed it from the derivable set is superseded
 * only in that sense — it is NOT re-added to the BR-2 lock.
 *
 * User directive 2026-07-21/2026-07-22: the lead's Operator carries
 * `managerSlots`/`managerRefs` (the "Gestore Account 2" prefill, with an empty
 * G.A. 1 slot before it), NOT `supervisor_id`
 * anymore — the Supervisor is left empty. Like the former supervisor
 * suggestion it is deliberately OUT of `DERIVED_FIELDS`/`lockedFields()`, a
 * plain editable prefill the user may freely change or clear.
 */
final class LeadOpportunityDefaultsResolver
{
    /**
     * The 2 BR-1-derivable/lockable field keys, in the contract's declared
     * order.
     *
     * @var array<int, string>
     */
    private const array DERIVED_FIELDS = [
        'source_id',
        'registry_id',
    ];

    /**
     * Relations resolve() needs loaded on $lead to stay N+1-free; harmless
     * (loadMissing) when the caller already eager-loaded some or all of them.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_RELATIONS = [
        'registry',
        'source',
        'operator',
        'operationalSite.addresses.city',
        'opportunity',
        'campaign.businessFunction',
        'campaign.productCategory',
        'campaign.project.businessFunction',
        'campaign.project.productCategory',
    ];

    public function resolve(Lead $lead): LeadOpportunityDefaults
    {
        $lead->loadMissing(self::REQUIRED_RELATIONS);

        $campaign = $lead->campaign;

        $effectiveSource = $lead->source;

        $values = [
            'source_id' => $effectiveSource?->id,
            'registry_id' => $lead->registry_id,
            // spec 0047 (AC-002, D1): the Regione inherited from the lead's
            // own state_id. NOT in DERIVED_FIELDS — a plain default value,
            // never BR-2-locked (an opportunity's Regione stays editable even
            // when it originates from a lead).
            'state_id' => $lead->state_id,
            // User directive 2026-07-23: the Sede operativa is inherited on
            // conversion. NOT in DERIVED_FIELDS — a plain editable default,
            // never BR-2-locked.
            'operational_site_id' => $lead->operational_site_id,
            // User directive 2026-07-27: the opportunity's "Note generali"
            // are seeded from the lead's own free-text notes. Same plain,
            // never-locked treatment as the two defaults above — the only
            // non-id entry of this map.
            'general_notes' => $lead->notes,
        ];

        $references = [
            'source' => $this->summarizeByName($effectiveSource),
            'registry' => $this->summarizeByName($lead->registry),
            // The site has no `name` column: its identity is the composed
            // "{line1} - {city}" label (OperationalSiteLabel), so this entry
            // is a {id,label} summary, not a {id,name} one.
            'operational_site' => OperationalSiteLabel::summarize($lead->operationalSite),
        ];

        // User directive 2026-07-22: the lead's Operator seeds the SECOND
        // "Gestore Account" slot — G.A. 1 is materialized empty (leading null,
        // gap-aware) — never the Supervisor.
        $operator = $lead->operator;
        $managerRef = $this->summarizeByName($operator);

        return new LeadOpportunityDefaults(
            values: $values,
            references: $references,
            lockedFields: $this->lockedFields($values),
            productLines: $this->productLines($this->effectiveBusinessFunction($campaign), $this->effectiveProductCategory($campaign)),
            existingOpportunityId: $lead->opportunity?->id,
            managerSlots: $operator === null ? [] : [null, $operator->id],
            managerRefs: $managerRef === null ? [] : [$managerRef],
        );
    }

    /**
     * Whether a campaign (via its linked Project when one exists, else the
     * campaign's own business function/product category) derives a
     * non-empty Opportunity product line — the SAME predicate resolve()
     * uses, exposed for App\Services\Import\ImportOpportunityConvertibility
     * (the import wizard's pre-conversion gate, spec 0045) so the two never
     * drift apart. Caller must eager-load businessFunction/productCategory/
     * project.businessFunction/project.productCategory on $campaign.
     */
    public function campaignDerivesProductLine(Campaign $campaign): bool
    {
        return $this->productLines(
            $this->effectiveBusinessFunction($campaign),
            $this->effectiveProductCategory($campaign),
        ) !== [];
    }

    private function effectiveBusinessFunction(Campaign $campaign): ?Model
    {
        return $campaign->project !== null ? $campaign->project->businessFunction : $campaign->businessFunction;
    }

    private function effectiveProductCategory(Campaign $campaign): ?Model
    {
        return $campaign->project !== null ? $campaign->project->productCategory : $campaign->productCategory;
    }

    /**
     * A single derived row {business_function, product_category} when BOTH
     * the campaign/project's effective business function AND product
     * category are present, else an empty list (amendment rev.3: this row is
     * EDITABLE/removable in the form, never BR-2-locked).
     *
     * @return array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>
     */
    private function productLines(?Model $businessFunction, ?Model $productCategory): array
    {
        if ($businessFunction === null || $productCategory === null) {
            return [];
        }

        return [[
            'business_function' => ['id' => $businessFunction->id, 'name' => $businessFunction->name],
            'product_category' => ['id' => $productCategory->id, 'name' => $productCategory->name],
        ]];
    }

    /**
     * @param  array<string, int|string|null>  $values
     * @return array<int, string>
     */
    private function lockedFields(array $values): array
    {
        return array_values(array_filter(
            self::DERIVED_FIELDS,
            static fn (string $field): bool => $values[$field] !== null,
        ));
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }
}
