import { useMemo } from 'react'
import { useWatch, type Control } from 'react-hook-form'
import { COMPANIES_FOR_SELECT_RESOURCE } from '@/features/companies/for-select-api'
import { DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE } from '@/features/document-layouts/for-select-api'
import type { ForSelectItem } from '@/features/for-select/types'
import { useForSelectLabels } from '@/features/for-select/use-for-select'
import { PAYMENT_METHODS_FOR_SELECT_RESOURCE } from '@/features/payment-methods/for-select-api'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { idToSelectValue, NO_ID, type EditorSource } from '@/features/invoices/invoice-editor-source'
import type { InvoiceRecordDisplay } from '@/features/invoices/invoice-record-sections'
import type { InvoiceFormValues } from '@/features/invoices/invoice-schema'
import type { NamedRef } from '@/features/invoices/types'

/** Shown while a label resolves: never the bare id. */
const PENDING_LABEL = '…'
const INVOICES_LAYOUT_PARAMS = { module: 'invoices' } as const
const NO_IDS: number[] = []

function idsOf(id: number | null): number[] {
  return id === null ? NO_IDS : [id]
}

/** `{id, name}` of a draft relation: the ref the draft came with wins, then the picker's label cache. */
function refOf(id: number | null, labels: Map<number, ForSelectItem>, known: NamedRef | null): NamedRef | null {
  if (id === null) {
    return null
  }
  if (known && known.id === id) {
    return known
  }
  return { id, name: labels.get(id)?.label ?? PENDING_LABEL }
}

/**
 * Names every relation the issue draft holds as a bare id, so its CLOSED rows
 * read like the detail's (spec 0224 D-5): through the refs of the draft and
 * the for-select label cache the pickers fill for their own trigger.
 */
export function useInvoiceDraftDisplay(control: Control<InvoiceFormValues>, source: EditorSource): InvoiceRecordDisplay {
  const values = useWatch({ control })
  const companyId = idToSelectValue(values.company_id ?? NO_ID)
  const customerId = idToSelectValue(values.customer_registry_id ?? NO_ID)
  const paymentMethodId = idToSelectValue(values.payment_method_id ?? NO_ID)
  const layoutId = values.layout_id ?? null
  const bankId = values.financial_account_id ?? null

  const companies = useForSelectLabels({ resource: COMPANIES_FOR_SELECT_RESOURCE, ids: idsOf(companyId) })
  const customers = useForSelectLabels({ resource: REGISTRIES_FOR_SELECT_RESOURCE, ids: idsOf(customerId) })
  const paymentMethods = useForSelectLabels({ resource: PAYMENT_METHODS_FOR_SELECT_RESOURCE, ids: idsOf(paymentMethodId) })
  const layouts = useForSelectLabels({
    resource: DOCUMENT_LAYOUTS_FOR_SELECT_RESOURCE,
    ids: idsOf(layoutId),
    params: INVOICES_LAYOUT_PARAMS,
  })
  const bank = useMemo(
    () => source.bankAccounts.find((candidate) => candidate.id === bankId) ?? null,
    [source.bankAccounts, bankId],
  )

  return {
    documentDate: values.document_date ?? '',
    company: refOf(companyId, companies, source.company),
    customer: refOf(customerId, customers, source.customer),
    paymentMethod: refOf(paymentMethodId, paymentMethods, source.paymentMethod),
    financialAccount: bank,
    layout: refOf(layoutId, layouts, source.layout),
    tag: values.tag ?? null,
    notes: values.notes ?? null,
    internalNote: values.internal_note ?? null,
  }
}
