import { useMemo } from 'react'
import type { AxiosError } from 'axios'
import { useInvoiceDraft } from '@/features/invoices/use-invoice-queries'
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

/** Everything the issue form needs, normalized from the create draft. */
export interface EditorSource {
  initialValues: InvoiceWriteFormValues
  vatRefs: Record<number, VatRateRef>
  availableLines: AvailableInvoiceLine[]
  /** Candidate banks per company. */
  bankAccounts: EditorBankAccount[] | null
  company: NamedRef | null
  customer: NamedRef | null
  paymentMethod: NamedRef | null
  financialAccount: BankAccountRef | null
  /** The draft's print layout; null for "Predefinito". */
  layout: NamedRef | null
  context: EditorContext
}

export function vatRefsOf(lines: Array<{ vat_rate: VatRateRef | null }>): Record<number, VatRateRef> {
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
      layout_id: defaults.layout?.id ?? null,
      notes: defaults.notes,
      internal_note: null,
      invoice_tag_id: null,
      lines: [],
    },
    vatRefs: vatRefsOf(draft.available_lines),
    availableLines: draft.available_lines,
    bankAccounts: draft.bank_accounts,
    company: defaults.company,
    customer: defaults.customer,
    paymentMethod: defaults.payment_method,
    financialAccount: defaults.financial_account,
    layout: defaults.layout,
    context: {
      workOrderCode: draft.work_order.code,
      quoteCode: draft.quote.code,
      kind: draft.proforma_request.kind,
      note: draft.proforma_request.note,
    },
  }
}

/** The saved document as form values: what its record edits in place (spec 0263). */
export function valuesFromInvoice(invoice: Invoice): InvoiceWriteFormValues {
  return {
    document_date: invoice.document_date,
    company_id: invoice.company.id,
    customer_registry_id: invoice.customer.id,
    payment_method_id: invoice.payment_method.id,
    financial_account_id: invoice.financial_account?.id ?? null,
    layout_id: invoice.layout?.id ?? null,
    notes: invoice.notes,
    internal_note: invoice.internal_note,
    invoice_tag_id: invoice.tag?.id ?? null,
    lines: invoice.lines.map((line) => ({
      quote_line_id: line.quote_line_id,
      product_id: line.product?.id ?? null,
      description: line.description,
      quantity: Number(line.quantity),
      unit_price: Number(line.unit_price),
      vat_rate_id: line.vat_rate.id,
    })),
  }
}

export interface EditorSourceState {
  source: EditorSource | null
  isLoading: boolean
  error: AxiosError | null
}

/** Loads the draft of the proforma request being invoiced; idle while null. */
export function useInvoiceEditorSource(proformaRequestId: number | null): EditorSourceState {
  const draft = useInvoiceDraft(proformaRequestId)
  const source = useMemo(() => (draft.data ? sourceFromDraft(draft.data) : null), [draft.data])

  return { source, isLoading: draft.isPending, error: draft.error }
}
