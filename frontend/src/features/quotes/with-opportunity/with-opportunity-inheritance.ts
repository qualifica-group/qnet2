import type { UseFormSetValue } from 'react-hook-form'
import { padManagerSlots, sameManagerSlots } from '@/lib/utils'
import { DEFAULT_MANAGER_SLOTS } from '@/features/quotes/quote-schema'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'

/**
 * What the offer takes from the opportunity being created (spec 0396 D-5): the
 * same fields an offer inherits from an existing opportunity
 * (`useQuoteCreateDefaults.applyInheritedValues`), read from step 1's draft.
 */
export interface InheritedFields {
  commercial_id: number | null
  reporter_id: number | null
  supervisor_id: number | null
  operational_site_id: number | null
  manager_slots: (number | null)[]
}

type ScalarKey = Exclude<keyof InheritedFields, 'manager_slots'>

const SCALAR_KEYS: readonly ScalarKey[] = ['commercial_id', 'reporter_id', 'supervisor_id', 'operational_site_id']

export function inheritedFromOpportunity(values: OpportunityFormValues): InheritedFields {
  return {
    commercial_id: values.commercial_id,
    reporter_id: values.reporter_id,
    supervisor_id: values.supervisor_id,
    operational_site_id: values.operational_site_id,
    manager_slots: padManagerSlots(values.manager_slots, DEFAULT_MANAGER_SLOTS),
  }
}

function isBlankSlots(slots: (number | null)[]): boolean {
  return slots.every((slot) => slot === null)
}

/**
 * The fields to write on the offer so it follows step 1 again, WITHOUT undoing
 * what the operator typed on it (spec 0396 D-5). A field counts as untouched
 * while it still holds what was last copied (`previous`), or is blank when
 * nothing was copied yet; a touched one is left alone.
 */
export function alignInheritedFields(
  current: Pick<QuoteFormValues, ScalarKey | 'manager_slots'>,
  previous: InheritedFields | null,
  next: InheritedFields,
): Partial<InheritedFields> {
  const patch: Partial<InheritedFields> = {}

  for (const key of SCALAR_KEYS) {
    const untouched = previous === null ? current[key] === null : current[key] === previous[key]
    if (untouched && current[key] !== next[key]) {
      patch[key] = next[key]
    }
  }

  const slotsUntouched =
    previous === null ? isBlankSlots(current.manager_slots) : sameManagerSlots(current.manager_slots, previous.manager_slots)
  if (slotsUntouched && !sameManagerSlots(current.manager_slots, next.manager_slots)) {
    patch.manager_slots = next.manager_slots
  }

  return patch
}

/** Writes an alignment patch on the offer form; the writes mark the fields dirty like a pick on an existing opportunity does. */
export function applyInheritedPatch(setValue: UseFormSetValue<QuoteFormValues>, patch: Partial<InheritedFields>): void {
  const options = { shouldDirty: true }
  if (patch.commercial_id !== undefined) {
    setValue('commercial_id', patch.commercial_id, options)
  }
  if (patch.reporter_id !== undefined) {
    setValue('reporter_id', patch.reporter_id, options)
  }
  if (patch.supervisor_id !== undefined) {
    setValue('supervisor_id', patch.supervisor_id, options)
  }
  if (patch.operational_site_id !== undefined) {
    setValue('operational_site_id', patch.operational_site_id, options)
  }
  if (patch.manager_slots !== undefined) {
    setValue('manager_slots', patch.manager_slots, options)
  }
}
