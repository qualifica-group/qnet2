import { useMemo } from 'react'
import type { AxiosError } from 'axios'
import { todayIso } from '@/features/invoices/invoice-format'
import type { BankAccountRef, NamedRef } from '@/features/invoices/types'
import {
  lineFromDraft,
  lineFromInvoice,
  NO_ID,
  vatRefsOf,
  type VatRefs,
} from '@/features/purchase-invoices/purchase-invoice-editor-lines'
import type { PurchaseInvoiceWriteFormValues } from '@/features/purchase-invoices/purchase-invoice-schema'
import { usePurchaseInvoiceDraft } from '@/features/purchase-invoices/use-purchase-invoice-editor-queries'
import type { PurchaseInvoice, PurchaseInvoiceDraft } from '@/features/purchase-invoices/types'

/** Everything the create form needs, normalized from a blank form or an ODA draft. */
export interface EditorSource {
  initialValues: PurchaseInvoiceWriteFormValues
  vatRefs: VatRefs
  company: NamedRef | null
  supplier: NamedRef | null
  paymentMethod: NamedRef | null
  financialAccount: BankAccountRef | null
  companySite: NamedRef | null
  operationalSite: NamedRef | null
  costType: NamedRef | null
  workOrder: NamedRef | null
}

const NO_LINES: PurchaseInvoiceWriteFormValues['lines'] = []

function workOrderRef(workOrder: { id: number; code: string; title: string | null } | null | undefined): NamedRef | null {
  return workOrder ? { id: workOrder.id, name: [workOrder.code, workOrder.title].filter(Boolean).join(' - ') } : null
}

function idOf(ref: { id: number } | null | undefined): number | null {
  return ref?.id ?? null
}

/** Empty create form: the user picks supplier and company, then optionally adds ODAs. */
export function blankSource(): EditorSource {
  return {
    initialValues: {
      document_type: 'invoice',
      document_number: null,
      document_date: todayIso(),
      company_id: NO_ID,
      supplier_id: NO_ID,
      payment_method_id: NO_ID,
      financial_account_id: null,
      company_site_id: null,
      operational_site_id: null,
      cost_type_id: null,
      work_order_id: null,
      notes: null,
      purchase_order_ids: [],
      lines: NO_LINES,
    },
    vatRefs: {},
    company: null,
    supplier: null,
    paymentMethod: null,
    financialAccount: null,
    companySite: null,
    operationalSite: null,
    costType: null,
    workOrder: null,
  }
}

export function sourceFromDraft(draft: PurchaseInvoiceDraft): EditorSource {
  const { defaults } = draft
  const blank = blankSource()
  return {
    ...blank,
    initialValues: {
      ...blank.initialValues,
      company_id: defaults.company.id,
      supplier_id: defaults.supplier.id,
      payment_method_id: defaults.payment_method?.id ?? NO_ID,
      company_site_id: idOf(defaults.company_site),
      operational_site_id: idOf(defaults.operational_site),
      cost_type_id: idOf(defaults.cost_type),
      work_order_id: idOf(defaults.work_order),
      purchase_order_ids: draft.purchase_order_ids,
      lines: draft.lines.map(lineFromDraft),
    },
    vatRefs: vatRefsOf(draft.lines),
    company: defaults.company,
    supplier: defaults.supplier,
    paymentMethod: defaults.payment_method,
    companySite: defaults.company_site,
    operationalSite: defaults.operational_site,
    costType: defaults.cost_type,
    workOrder: workOrderRef(defaults.work_order),
  }
}

/** The saved document as form values: what its record edits in place (spec 0263). */
export function valuesFromInvoice(invoice: PurchaseInvoice): PurchaseInvoiceWriteFormValues {
  return {
    document_type: invoice.document_type,
    document_number: invoice.document_number,
    document_date: invoice.document_date,
    company_id: invoice.company.id,
    supplier_id: invoice.supplier.id,
    payment_method_id: invoice.payment_method.id,
    financial_account_id: idOf(invoice.financial_account),
    company_site_id: idOf(invoice.company_site),
    operational_site_id: idOf(invoice.operational_site),
    cost_type_id: idOf(invoice.cost_type),
    work_order_id: idOf(invoice.work_order),
    notes: invoice.notes,
    purchase_order_ids: invoice.purchase_orders.map((order) => order.id),
    lines: invoice.lines.map(lineFromInvoice),
  }
}

export interface EditorSourceState {
  source: EditorSource | null
  isLoading: boolean
  error: AxiosError | null
}

/** Loads the ODA draft of the orders to register; a plain create resolves immediately to the blank form. */
export function usePurchaseInvoiceEditorSource(orderIds: readonly number[] | null): EditorSourceState {
  const draft = usePurchaseInvoiceDraft(orderIds)

  const fromDraft = useMemo(() => (draft.data ? sourceFromDraft(draft.data) : null), [draft.data])
  const blank = useMemo(() => blankSource(), [])

  if (orderIds !== null && orderIds.length > 0) {
    return { source: fromDraft, isLoading: draft.isPending, error: draft.error }
  }
  return { source: blank, isLoading: false, error: null }
}
