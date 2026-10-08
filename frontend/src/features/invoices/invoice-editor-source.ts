import { useMemo } from 'react'
import type { AxiosError } from 'axios'
import { useInvoiceDraft, useInvoice } from '@/features/invoices/use-invoice-queries'
import type { InvoiceWriteFormValues } from '@/features/invoices/invoice-schema'
import type {
  AvailableInvoiceLine,
  BankAccountRef,
  Invoice,
  InvoiceDraft,
  NamedRef,
  VatRateRef,
} from '@/features/invoices/types'
import type { ProformaRequestKind } from '@/features/proforma-requests/types'

/** Form sentinel of an unselected required id (zod `z.number()` rejects NaN with the field message). */
export const NO_ID = Number.NaN

export function idToSelectValue(id: number): number | null {
  return Number.isNaN(id) ? null : id
}

export function selectValueToId(id: number | null): number {
  return id ?? NO_ID
}

export type EditorBankAccount = BankAccountRef & { company_id: number }

/** Read-only context shown in the modal header. */
export interface EditorContext {
  workOrderCode: string | null
  quoteCode: string | null
  kind: ProformaRequestKind | null
  note: string | null
}

/** Everything the form needs, normalized from the create draft or the edited document. */
export interface EditorSource {
  initialValues: InvoiceWriteFormValues
  vatRefs: Record<number, VatRateRef>
  availableLines: AvailableInvoiceLine[]
  /** Candidate banks per company; null in edit mode (the draft is not loaded there). */
  bankAccounts: EditorBankAccount[] | null
  company: NamedRef | null
  customer: NamedRef | null
  paymentMethod: NamedRef | null
  financialAccount: BankAccountRef | null
  context: EditorContext
  /** "12/2026" when editing. */
  numberLabel: string | null
  /** The edited document has collected installments: date, payment method and customer are read-only (spec 0196, D-1). */
  hasCollections: boolean
}

const NO_AVAILABLE_LINES: AvailableInvoiceLine[] = []

function vatRefsOf(lines: Array<{ vat_rate: VatRateRef | null }>): Record<number, VatRateRef> {
  return Object.fromEntries(lines.flatMap((line) => (line.vat_rate === null ? [] : [[line.vat_rate.id, line.vat_rate]])))
}

export function sourceFromDraft(draft: InvoiceDraft): EditorSource {
  const { defaults } = draft
  return {
    initialValues: {
      document_date: defaults.document_date,
      company_id: defaults.company?.id ?? NO_ID,
      customer_registry_id: defaults.customer?.id ?? NO_ID,
      payment_method_id: defaults.payment_method?.id ?? NO_ID,
      financial_account_id: defaults.financial_account?.id ?? null,
      notes: defaults.notes,
      internal_note: null,
      tag: null,
      lines: [],
    },
    vatRefs: vatRefsOf(draft.available_lines),
    availableLines: draft.available_lines,
    bankAccounts: draft.bank_accounts,
    company: defaults.company,
    customer: defaults.customer,
    paymentMethod: defaults.payment_method,
    financialAccount: defaults.financial_account,
    context: {
      workOrderCode: draft.work_order.code,
      quoteCode: draft.quote.code,
      kind: draft.proforma_request.kind,
      note: draft.proforma_request.note,
    },
    numberLabel: null,
    hasCollections: false,
  }
}

export function sourceFromInvoice(invoice: Invoice): EditorSource {
  return {
    initialValues: {
      document_date: invoice.document_date,
      company_id: invoice.company.id,
      customer_registry_id: invoice.customer.id,
      payment_method_id: invoice.payment_method.id,
      financial_account_id: invoice.financial_account?.id ?? null,
      notes: invoice.notes,
      internal_note: invoice.internal_note,
      tag: invoice.tag,
      lines: invoice.lines.map((line) => ({
        quote_line_id: line.quote_line_id,
        product_id: line.product?.id ?? null,
        description: line.description,
        quantity: Number(line.quantity),
        unit_price: Number(line.unit_price),
        vat_rate_id: line.vat_rate.id,
      })),
    },
    vatRefs: vatRefsOf(invoice.lines),
    availableLines: NO_AVAILABLE_LINES,
    bankAccounts: null,
    company: invoice.company,
    customer: { id: invoice.customer.id, name: invoice.customer.name },
    paymentMethod: invoice.payment_method,
    financialAccount: invoice.financial_account,
    context: {
      workOrderCode: invoice.work_order?.code ?? null,
      quoteCode: invoice.quote?.code ?? null,
      kind: invoice.proforma_request?.kind ?? null,
      note: null,
    },
    numberLabel: invoice.number_label,
    hasCollections: invoice.has_collections,
  }
}

export interface EditorSourceState {
  source: EditorSource | null
  isLoading: boolean
  error: AxiosError | null
}

/** Loads the draft (create) or the document (edit); the other query stays idle. */
export function useInvoiceEditorSource(target: {
  proformaRequestId: number | null
  invoiceId: number | null
}): EditorSourceState {
  const draft = useInvoiceDraft(target.proformaRequestId)
  const invoice = useInvoice(target.invoiceId)

  const fromDraft = useMemo(() => (draft.data ? sourceFromDraft(draft.data) : null), [draft.data])
  const fromInvoice = useMemo(() => (invoice.data ? sourceFromInvoice(invoice.data) : null), [invoice.data])

  if (target.proformaRequestId !== null) {
    return { source: fromDraft, isLoading: draft.isPending, error: draft.error }
  }
  return { source: fromInvoice, isLoading: invoice.isPending, error: invoice.error }
}
