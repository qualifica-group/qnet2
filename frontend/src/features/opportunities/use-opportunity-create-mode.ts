import { useOpportunityDefaults } from '@/features/opportunities/use-opportunity-defaults'
import type { OpportunityCreateFormMode } from '@/features/opportunities/types'

/**
 * Resolves what `/opportunities/new` (optionally `?lead_id=N`) should render
 * (spec 0040 MT-6). A plain create (`leadId === null`) is immediately
 * `'ready'` with no request at all. With a `leadId`, the defaults are loaded
 * first: an already-linked lead (D-2) short-circuits to `'existing'` instead
 * of a form the server would reject with a 422 unique on submit.
 */
export type OpportunityCreateModeState =
  | { status: 'loading' }
  | { status: 'error'; retry: () => void }
  | { status: 'existing'; existingOpportunityId: number }
  | { status: 'ready'; mode: OpportunityCreateFormMode }

export function useOpportunityCreateMode(leadId: number | null): OpportunityCreateModeState {
  const defaultsQuery = useOpportunityDefaults(leadId)

  if (leadId === null) {
    return { status: 'ready', mode: { type: 'create' } }
  }
  if (defaultsQuery.isError) {
    return { status: 'error', retry: () => void defaultsQuery.refetch() }
  }
  if (!defaultsQuery.data) {
    return { status: 'loading' }
  }

  const defaults = defaultsQuery.data
  if (defaults.existing_opportunity_id !== null) {
    return { status: 'existing', existingOpportunityId: defaults.existing_opportunity_id }
  }

  return {
    status: 'ready',
    mode: {
      type: 'create',
      fromLead: {
        leadId: defaults.lead_id,
        values: defaults.values,
        references: defaults.references,
        lockedFields: defaults.locked_fields,
        productLines: defaults.product_lines,
        managerSlots: defaults.manager_slots,
      },
    },
  }
}
