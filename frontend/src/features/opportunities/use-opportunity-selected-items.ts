import { useMemo } from 'react'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import type { ForSelectItem } from '@/features/for-select/types'
import type { OpportunityFormMode } from '@/features/opportunities/types'
import type { OpportunityLeadSelectionState } from '@/features/opportunities/use-opportunity-lead-selection'

/** Every relation picker's edit-mode hydration, resolved once by the form hook (mirrors `RegistrySelectedItems`). */
export interface OpportunitySelectedItems {
  registry: RelationFieldRef | null
  /** Spec 0043 D-3: the mandatory opportunity status, always set in edit mode. */
  referent: RelationFieldRef | null
  commercial: RelationFieldRef | null
  reporter: RelationFieldRef | null
  source: RelationFieldRef | null
  /** Spec 0056: the operational site's `{id,label}` ref, converted to `{id,name}` — from the loaded opportunity in edit mode, or inherited from the lead on a conversion (directive 2026-07-23). */
  operationalSite: RelationFieldRef | null
  /** Spec 0047 (D1): the Regione's hydrated ref, edit mode only (never known before a create-from-lead is saved). */
  state: RelationFieldRef | null
  supervisor: RelationFieldRef | null
  managers: ForSelectItem[]
}

const EMPTY_SELECTED_ITEMS: OpportunitySelectedItems = {
  registry: null,
  referent: null,
  commercial: null,
  reporter: null,
  source: null,
  operationalSite: null,
  state: null,
  supervisor: null,
  managers: [],
}

/**
 * Resolves every relation picker's already-known `{id, name}` hydration: from
 * the loaded instance in edit mode, or from the Lead's derived `references`
 * (spec 0040 MT-6) in a create-from-lead — so each `AsyncPaginatedSelect`
 * shows its (possibly locked) current selection immediately, no hydration
 * round-trip. A plain manual create has nothing to hydrate. `business_function`/
 * `product_category` are NOT part of this shape anymore (spec 0040 amendment
 * rev.3): the shared `ProductLinesField` (spec 0057) resolves its own row labels.
 *
 * `leadSelection.registry` (spec 0041 AC-051) takes precedence over
 * `mode.fromLead.references.registry`: it reflects the in-form "Lead" picker,
 * which can supersede the initial deep-link lead after mount, while the
 * latter only ever hydrates the very first render. `managers` mirrors the
 * same precedence (directive 2026-07-21): `leadSelection.managers` is set only
 * right after a fresh in-form selection actually appended the lead's Operator
 * as a "Gestore Account" slot, so it naturally falls back to
 * `mode.fromLead.managerRefs` (deep-link mount) or `[]` (no prefill happened —
 * an already-chosen manager keeps its own label, resolved by the slot picker
 * itself, not by this hydration). The Supervisor is no longer prefilled from
 * the lead; it only hydrates in edit mode.
 */
export function useOpportunitySelectedItems(
  mode: OpportunityFormMode,
  leadSelection: OpportunityLeadSelectionState,
): OpportunitySelectedItems {
  return useMemo(() => {
    if (mode.type === 'edit') {
      const { opportunity } = mode
      return {
        registry: opportunity.registry,
        referent: opportunity.referent,
        commercial: opportunity.commercial,
        reporter: opportunity.reporter,
        source: opportunity.source,
        operationalSite: toRelationFieldRef(opportunity.operational_site ?? null),
        state: opportunity.state ?? null,
        supervisor: opportunity.supervisor,
        managers: opportunity.managers.map((manager) => ({ id: manager.id, label: manager.name })),
      }
    }
    const fromLead = mode.fromLead
    if (!fromLead && !leadSelection.registry) {
      return EMPTY_SELECTED_ITEMS
    }
    // Directive 2026-07-22: the lead's Operator hydrates its "Gestore
    // Account" slot's trigger label (G.A. 2), not the Supervisor.
    const managerRefs = leadSelection.managers ?? fromLead?.managerRefs ?? []
    return {
      ...EMPTY_SELECTED_ITEMS,
      registry: leadSelection.registry ?? fromLead?.references.registry ?? null,
      source: fromLead?.references.source ?? null,
      // Directive 2026-07-23: same precedence as `registry` — the in-form
      // lead pick supersedes the deep-link's initial hydration.
      operationalSite:
        leadSelection.operationalSite ?? toRelationFieldRef(fromLead?.references.operational_site ?? null),
      managers: managerRefs.map((ref) => ({ id: ref.id, label: ref.name })),
    }
  }, [mode, leadSelection.registry, leadSelection.operationalSite, leadSelection.managers])
}
