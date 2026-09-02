<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * The BR-1-derived values for an Opportunity generated from a Lead (spec
 * 0040), computed by LeadOpportunityDefaultsResolver: the single source of
 * truth consumed BOTH by `GET /api/leads/{lead}/opportunity-defaults` (the
 * create-form prefill) and by the store/update enforcement (BR-2 lock).
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REMOVED
 * from `values`/`references`/`lockedFields` (no longer derivable/lockable —
 * the rows they used to populate are now EDITABLE/removable in the form,
 * never BR-2-locked); `productLines` carries the 0..N rows derived from the
 * lead/campaign's EFFECTIVE product lines (spec 0094, D-1/AC-060).
 *
 * User directive 2026-07-21/2026-07-22: the lead's Operator no longer prefills
 * the Opportunity's Supervisor — it seeds the SECOND "Gestore Account" slot
 * instead (`managerSlots`/`managerRefs`), G.A. 1 staying an empty slot, and the
 * Supervisor stays empty. The lead has at most one Operator (AC-025), never
 * locked.
 */
final readonly class LeadOpportunityDefaults
{
    /**
     * @param  array<string, int|string|null>  $values  the 2 derivable fields (source_id/registry_id) plus the plain, never-locked defaults (operational_site_id, general_notes — the only non-id entry)
     * @param  array<string, array{id: int, name: string}|array{id: int, label: string}|null>  $references  {id,name} summaries, except `operational_site` which is a composed {id,label} (the site has no `name` column)
     * @param  array<int, string>  $lockedFields  the subset of $values whose derivation is non-null (BR-2)
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines  0..N rows, editable/removable in the form (never locked)
     * @param  array<int, int|null>  $managerSlots  empty, or the gap-aware [null, operator_id]: an empty G.A. 1 plus the lead's Operator as G.A. 2 (never locked)
     * @param  array<int, array{id: int, name: string}>  $managerRefs  {id,name} summaries of the filled slots, for the slot's trigger-label hydration
     */
    public function __construct(
        public array $values,
        public array $references,
        public array $lockedFields,
        public array $productLines,
        public ?int $existingOpportunityId,
        public array $managerSlots,
        public array $managerRefs,
    ) {}
}
