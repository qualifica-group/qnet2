import { useState } from 'react'
import { useQueryClient } from '@tanstack/react-query'
import type { UseFormGetValues, UseFormSetValue } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { toRelationFieldRef } from '@/components/form/relation-field-ref'
import { fetchOpportunityDefaultsOnce } from '@/features/opportunities/opportunity-defaults-api'
import type { OpportunityProductLine } from '@/features/opportunities/types'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/**
 * The 2 BR-1 fields a lead selection writes to (or clears from) the form.
 * `referent_id` is NOT among them (spec 0041 D-3): it stays a free,
 * anagrafica-scoped pick (BR-4 spec 0040), never derived/locked by the lead.
 * `business_function_id`/`product_category_id` are NOT single fields anymore
 * (spec 0040 amendment rev.3): the lead's derived function+category, when
 * both exist, seeds `product_lines` instead (handled separately below).
 */
const DERIVED_FIELDS = ['source_id', 'registry_id'] as const

export interface OpportunityLeadSelectionState {
  leadId: number | null
  /** BR-2: keys locked by the current selection. Empty while none is selected, or while `existingOpportunityId` blocks the flow. */
  lockedFields: string[]
  /** D-2: the lead is already linked to another opportunity — the form must block the submit, never write derived values. */
  existingOpportunityId: number | null
  /** The lead's anagrafica (its identity, spec 0041 D-3): the single source for both the Lead select's own trigger label and the registry picker's. */
  registry: RelationFieldRef | null
  /**
   * Directive 2026-07-23: the Sede operativa inherited from the lead, set on
   * a successful selection so the site picker's trigger shows its label the
   * same way `registry` does — `setValue` alone writes the id, not the label.
   */
  operationalSite: RelationFieldRef | null
  /**
   * Directive 2026-07-21: the lead's Operator, set ONLY when this selection
   * just appended it as a new "Gestore Account" slot — `null` otherwise (the
   * Operator was already among the slots, or the lead has none). Feeds the
   * slot's trigger-label hydration the same way `registry` feeds the registry
   * picker's, since `setValue` alone writes the id but not the display name.
   */
  managers: RelationFieldRef[] | null
  /**
   * The lead's derived product line (spec 0040 amendment rev.3, AC-102/103),
   * 0 or 1 row: seeded into `product_lines` on a successful selection,
   * editable and removable like any other row. Kept here (not just written
   * to the form) so the shared `ProductLinesField` (spec 0057) can resolve
   * its label without a redundant fetch.
   */
  derivedProductLines: OpportunityProductLine[]
  /** True while the one-shot defaults fetch triggered by a fresh selection is in flight. */
  isApplying: boolean
  /** The defaults fetch failed; the selection was rolled back to "none". */
  isError: boolean
}

const EMPTY_STATE: OpportunityLeadSelectionState = {
  leadId: null,
  lockedFields: [],
  existingOpportunityId: null,
  registry: null,
  operationalSite: null,
  managers: null,
  derivedProductLines: [],
  isApplying: false,
  isError: false,
}

/**
 * G.A. 2 — the slot the lead's Operatore belongs to (directive 2026-07-22,
 * the `Opportunity::OPERATOR_MANAGER_POSITION` pivot position in the slots'
 * 0-based vocabulary).
 */
const OPERATOR_SLOT_INDEX = 1

/**
 * Seeds $operatorId into G.A. 2, materializing the slots up to it when the
 * form has fewer (it opens on four since the directive 2026-07-29, but a
 * removed slot can take it below). An operator ALREADY holding that slot is
 * never overwritten: the newcomer is appended after the existing ones
 * instead.
 */
function withOperatorSlot(slots: (number | null)[], operatorId: number): (number | null)[] {
  const next = [...slots]

  while (next.length <= OPERATOR_SLOT_INDEX) {
    next.push(null)
  }

  if (next[OPERATOR_SLOT_INDEX] === null) {
    next[OPERATOR_SLOT_INDEX] = operatorId

    return next
  }

  return [...next, operatorId]
}

export interface OpportunityLeadSelectionInitial {
  leadId: number
  lockedFields: string[]
  registry: RelationFieldRef | null
}

/**
 * Owns the in-form "Lead" picker's state (spec 0040 A-1, AC-086/087/088):
 * picking a lead applies BR-1's derived values and BR-2's locks EXACTLY like
 * the `?lead_id=N` deep-link — same one-shot fetch, same cache entry as
 * `useOpportunityDefaults` (`fetchOpportunityDefaultsOnce`), no second
 * implementation. Clearing the selection resets and unlocks the derived
 * fields AND replaces `product_lines` back to `[]` (spec 0040 amendment
 * rev.3): once seeded, a row is a normal editable/removable row
 * indistinguishable from a manually-added one, so this is the same "least
 * surprising" whole-field reset already applied to the other derived
 * fields — accepted even though it also discards any row the user added
 * manually before picking/clearing the lead. A lead already linked to
 * another opportunity (D-2) is surfaced without ever writing to the form.
 *
 * Directive 2026-07-22: selecting a lead also appends the lead's Operator as
 * a new "Gestore Account" slot — on an untouched form that means an empty
 * G.A. 1 plus the Operator on G.A. 2 — but ONLY when it isn't already among
 * the current slots: `getValues` is read at the exact moment of selection so
 * an existing manager selection is never overwritten, and a lead without an
 * Operator simply leaves the slots untouched. The Supervisor is no longer
 * prefilled from the lead.
 */
export function useOpportunityLeadSelection(
  initial: OpportunityLeadSelectionInitial | null,
  setValue: UseFormSetValue<OpportunityFormValues>,
  getValues: UseFormGetValues<OpportunityFormValues>,
) {
  const queryClient = useQueryClient()
  const [state, setState] = useState<OpportunityLeadSelectionState>(() =>
    initial
      ? {
          ...EMPTY_STATE,
          leadId: initial.leadId,
          lockedFields: initial.lockedFields,
          registry: initial.registry,
        }
      : EMPTY_STATE,
  )

  const applyProductLines = (lines: OpportunityProductLine[]) => {
    setValue(
      'product_lines',
      lines.map((line) => ({
        business_function_id: line.business_function.id,
        product_category_id: line.product_category.id,
      })),
      { shouldDirty: true },
    )
  }

  const clearDerivedFields = () => {
    for (const field of DERIVED_FIELDS) {
      setValue(field, null, { shouldDirty: true })
    }
    // Directive 2026-07-23: the inherited Sede operativa follows the same
    // whole-field reset as the other lead-seeded values when the selection is
    // cleared, even though it is never locked.
    setValue('operational_site_id', null, { shouldDirty: true })
    applyProductLines([])
  }

  const selectLead = async (leadId: number | null) => {
    if (leadId === null) {
      clearDerivedFields()
      setState(EMPTY_STATE)
      return
    }

    setState({ ...EMPTY_STATE, leadId, isApplying: true })
    try {
      const defaults = await fetchOpportunityDefaultsOnce(queryClient, leadId)

      if (defaults.existing_opportunity_id !== null) {
        setState({
          ...EMPTY_STATE,
          leadId,
          existingOpportunityId: defaults.existing_opportunity_id,
          registry: defaults.references.registry,
        })
        return
      }

      setValue('source_id', defaults.values.source_id, { shouldDirty: true })
      setValue('registry_id', defaults.values.registry_id, { shouldDirty: true })
      // Directive 2026-07-23: the Sede operativa is inherited from the lead,
      // editable afterwards like any plain field.
      setValue('operational_site_id', defaults.values.operational_site_id, { shouldDirty: true })
      applyProductLines(defaults.product_lines)

      // Directive 2026-07-22: seed the lead's Operator into the "Gestore
      // Account" slots, but only when it isn't already among them — never
      // overwrites an existing selection; a lead with no Operator (empty
      // `manager_slots`) is a no-op.
      let managers: RelationFieldRef[] | null = null
      const operatorId = defaults.manager_slots.find((id) => id !== null) ?? null
      if (operatorId !== null && !getValues('manager_slots').includes(operatorId)) {
        setValue('manager_slots', withOperatorSlot(getValues('manager_slots'), operatorId), { shouldDirty: true })
        managers = defaults.manager_refs
      }

      setState({
        leadId,
        lockedFields: defaults.locked_fields,
        existingOpportunityId: null,
        registry: defaults.references.registry,
        operationalSite: toRelationFieldRef(defaults.references.operational_site),
        managers,
        derivedProductLines: defaults.product_lines,
        isApplying: false,
        isError: false,
      })
    } catch {
      setState({ ...EMPTY_STATE, isError: true })
    }
  }

  return { state, selectLead }
}
