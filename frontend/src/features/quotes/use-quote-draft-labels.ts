import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { COMPANY_SITES_FOR_SELECT_RESOURCE } from '@/features/company-sites/for-select-api'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { OPERATIONAL_SITES_FOR_SELECT_RESOURCE } from '@/features/operational-sites/for-select-api'
import { OPPORTUNITIES_FOR_SELECT_RESOURCE, type OpportunityForSelectMeta } from '@/features/opportunities/for-select-api'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { QUOTES_LAYOUT_MODULE_PARAM } from '@/features/quotes/quote-field-strings'
import type { ForSelectItem } from '@/features/for-select/types'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteManagerRef } from '@/features/quotes/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'

/** Stable empty id set: a fresh `[]` per render would break the label hooks' memo. */
const NO_IDS: number[] = []

/** The draft's id(s) as the label hooks read them. */
function idsOf(...ids: (number | null | undefined)[]): number[] {
  const present = ids.filter((id): id is number => id != null)
  return present.length > 0 ? Array.from(new Set(present)) : NO_IDS
}

/**
 * `{id, name}` of a draft relation: the ref the create flow already holds
 * (inherited from the Opportunita', the default layout) wins, then the
 * for-select label cache, then a pending marker.
 */
function refOf(
  id: number | null | undefined,
  labels: Map<number, ForSelectItem>,
  known?: RelationFieldRef | null,
): RelationFieldRef | null {
  if (id == null) {
    return null
  }
  if (known && known.id === id) {
    return known
  }
  return { id, name: labels.get(id)?.label ?? PENDING_LABEL }
}

/** What the create flow already knows by name, before any label request returns. */
export interface QuoteDraftKnownRefs {
  inheritedMeta: OpportunityForSelectMeta | null
  opportunity: ForSelectItem | null
  layout: ForSelectItem | null
}

/**
 * Names every relation the create draft holds as a bare id, so its CLOSED
 * rows read like the detail's (spec 0197 D-6). The form holds ids only: the
 * rows resolve them through `useForSelectLabels`, the very cache the pickers
 * fill for their own trigger, and through the refs the flow already carries.
 */
export function useQuoteDraftLabels(control: Control<QuoteFormValues>, known: QuoteDraftKnownRefs) {
  const values = useWatch({ control })
  const { inheritedMeta } = known
  const slots = values.manager_slots ?? NO_IDS
  const companyId = values.company_id ?? null

  const opportunities = useForSelectLabels({
    resource: OPPORTUNITIES_FOR_SELECT_RESOURCE,
    ids: idsOf(values.opportunity_id),
  })
  const referents = useForSelectLabels({
    resource: REFERENTS_FOR_SELECT_RESOURCE,
    ids: idsOf(values.commercial_id, values.reporter_id),
  })
  const users = useForSelectLabels({
    resource: USERS_FOR_SELECT_RESOURCE,
    ids: idsOf(values.supervisor_id, ...slots),
  })
  const companies = useForSelectLabels({ resource: COMPANIES_FOR_SELECT_RESOURCE, ids: idsOf(companyId) })
  const companySites = useForSelectLabels({
    resource: COMPANY_SITES_FOR_SELECT_RESOURCE,
    ids: idsOf(values.company_site_id),
    params: companyId !== null ? { company_id: companyId } : undefined,
  })
  const operationalSites = useForSelectLabels({
    resource: OPERATIONAL_SITES_FOR_SELECT_RESOURCE,
    ids: idsOf(values.operational_site_id),
  })
  const layouts = useForSelectLabels({
    resource: DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE,
    ids: idsOf(values.layout_id),
    params: QUOTES_LAYOUT_MODULE_PARAM,
  })
  const paymentMethods = useForSelectLabels({
    resource: PAYMENT_METHODS_FOR_SELECT_RESOURCE,
    ids: idsOf(values.payment_method_id),
  })

  const inheritedSite = inheritedMeta?.operational_site
    ? { id: inheritedMeta.operational_site.id, name: inheritedMeta.operational_site.label }
    : null
  const managers = useMemo<QuoteManagerRef[]>(
    () =>
      slots.flatMap((id, index) => {
        if (id == null) {
          return []
        }
        const inherited = inheritedMeta?.managers.find((manager) => manager.id === id)
        return [{ id, name: inherited?.name ?? users.get(id)?.label ?? PENDING_LABEL, position: index + 1 }]
      }),
    [slots, inheritedMeta, users],
  )

  return {
    values,
    opportunity: refOf(
      values.opportunity_id,
      opportunities,
      known.opportunity ? { id: known.opportunity.id, name: known.opportunity.label } : null,
    ),
    commercial: refOf(values.commercial_id, referents, inheritedMeta?.commercial),
    reporter: refOf(values.reporter_id, referents, inheritedMeta?.reporter),
    supervisor: refOf(values.supervisor_id, users, inheritedMeta?.supervisor),
    managers,
    company: refOf(companyId, companies),
    companySite: refOf(values.company_site_id, companySites),
    operationalSite: refOf(values.operational_site_id, operationalSites, inheritedSite),
    layout: refOf(values.layout_id, layouts, known.layout ? { id: known.layout.id, name: known.layout.label } : null),
    paymentMethod: refOf(values.payment_method_id, paymentMethods),
  }
}
