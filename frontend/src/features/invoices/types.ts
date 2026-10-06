/**
 * Active invoicing types. Generic table types live in `features/table/types.ts`.
 * Source of truth: spec 0194 frozen `data_contract`. Money amounts coming from
 * the server are decimal STRINGS ("1234.50"); payload amounts are numbers.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { ProformaRequestKind } from '@/features/proforma-requests/types'

export const INVOICE_TYPES = ['proforma', 'invoice'] as const
export type InvoiceType = (typeof INVOICE_TYPES)[number]

/** Tab filter of the list: every document, or a single type. */
export type InvoiceTypeFilter = 'all' | InvoiceType

export const INVOICE_TAGS = ['estimate', 'final'] as const
export type InvoiceTag = (typeof INVOICE_TAGS)[number]

export const INVOICE_PAYMENT_STATUSES = ['paid', 'not_due', 'overdue', 'seriously_overdue'] as const
export type InvoicePaymentStatus = (typeof INVOICE_PAYMENT_STATUSES)[number]

export const INSTALLMENT_STATUSES = ['unpaid', 'partially_paid', 'paid'] as const
export type InstallmentStatus = (typeof INSTALLMENT_STATUSES)[number]

/** Row actions the SSRM rows may carry. */
export type InvoiceRowAction = 'view' | 'update' | 'details' | 'delete' | 'activity' | 'pdf' | 'email' | 'remind'

/** `{id, name}` projection of a related record. */
export interface NamedRef {
  id: number
  name: string
}

export interface BankAccountRef extends NamedRef {
  iban: string | null
}

export interface VatRateRef extends NamedRef {
  /** Percentage as decimal string, e.g. "22.00". */
  rate: string
}

export interface InvoiceLine {
  id: number
  quote_line_id: number | null
  product: NamedRef | null
  description: string
  quantity: string
  unit_price: string
  vat_rate: VatRateRef
  net_amount: string
  vat_amount: string
  total_amount: string
  sort_order: number
}

export interface InvoiceInstallment {
  id: number
  sequence: number
  due_date: string
  amount: string
  payment_method_code: string | null
  collected_amount: string | null
  collected_at: string | null
  status: InstallmentStatus
}

/** `InvoiceResource` (envelope `data`). */
export interface Invoice {
  id: number
  type: InvoiceType
  number: number
  year: number
  /** "12/2026". */
  number_label: string
  document_date: string
  company: NamedRef
  customer: { id: number; name: string; vat_number: string | null }
  payment_method: NamedRef
  financial_account: BankAccountRef | null
  /** Print layout saved on the document; null = the active default of the module. */
  layout: NamedRef | null
  proforma_request: { id: number; kind: ProformaRequestKind } | null
  work_order: { id: number; code: string; title: string | null } | null
  quote: { id: number; code: string } | null
  net_amount: string
  vat_amount: string
  total_amount: string
  collected_amount: string
  residual_amount: string
  payment_status: InvoicePaymentStatus
  external_number: string | null
  external_date: string | null
  notes: string | null
  internal_note: string | null
  tag: InvoiceTag | null
  deviation: string | null
  has_collections: boolean
  lines: InvoiceLine[]
  installments: InvoiceInstallment[]
  created_by: NamedRef
  created_at: string
  updated_at: string
}

/** GET/PUT/PATCH `/invoices/{id}` carry the actor's permissions as a sibling. */
export interface InvoiceWithPermissions extends Invoice {
  permissions: ResourcePermissions
}

/** One line of the write payload (POST create / PUT update). */
export interface InvoiceLinePayload {
  quote_line_id: number | null
  product_id: number | null
  description: string
  quantity: number
  unit_price: number
  vat_rate_id: number
}

/** `InvoiceWritePayload`. Totals are never sent: the server recomputes them. */
export interface InvoiceWritePayload {
  document_date: string
  company_id: number
  customer_registry_id: number
  payment_method_id: number
  financial_account_id: number | null
  /** Null = "Predefinito" (resolved at print time). */
  layout_id: number | null
  notes: string | null
  internal_note: string | null
  tag: InvoiceTag | null
  lines: InvoiceLinePayload[]
}

/** PATCH `/invoices/{id}/details` body. */
export interface InvoiceDetailsPayload {
  external_number: string | null
  external_date: string | null
  tag: InvoiceTag | null
  deviation: number | null
  internal_note: string | null
  layout_id: number | null
}

/** PUT `/invoice-installments/{id}/collection` body. */
export interface InvoiceCollectionPayload {
  collected_amount: number
  collected_at: string
}

/** A work-order line still available to be copied into the document. */
export interface AvailableInvoiceLine {
  quote_line_id: number
  product: NamedRef
  description: string
  quantity: string
  unit_price: string
  /** Null when neither the quote line nor its product has a VAT rate. */
  vat_rate: VatRateRef | null
  net_amount: string
  vat_amount: string
  total_amount: string
}

/** GET `/proforma-requests/{id}/invoice-draft` (envelope `data`). */
export interface InvoiceDraft {
  proforma_request: { id: number; kind: ProformaRequestKind; note: string }
  work_order: { id: number; code: string; title: string | null }
  quote: { id: number; code: string }
  defaults: {
    document_date: string
    company: NamedRef | null
    customer: NamedRef | null
    payment_method: NamedRef | null
    financial_account: BankAccountRef | null
    layout: NamedRef | null
    notes: string | null
  }
  available_lines: AvailableInvoiceLine[]
  bank_accounts: Array<BankAccountRef & { company_id: number }>
}

/** POST `/invoices/installment-preview` body (amounts are decimals). */
export interface InstallmentPreviewPayload {
  document_date: string
  payment_method_id: number
  net_amount: number
  vat_amount: number
  total_amount: number
}

export interface InstallmentPreviewRow {
  sequence: number
  due_date: string
  amount: string
  payment_method_code: string | null
}

export interface MonthlySummaryMonth {
  /** 1..12 */
  month: number
  count: number
  total_amount: string
}

/** GET `/invoices/monthly-summary` (envelope `data`); always 12 months. */
export interface MonthlySummary {
  year: number
  months: MonthlySummaryMonth[]
}

export interface MonthlySummaryParams {
  year: number
  type: InvoiceTypeFilter
}

/** SSRM row of the `invoices` table (`/tables/invoices/rows`). */
export interface InvoiceRow {
  id: number
  number_label: string
  number: number
  document_date: string
  type: InvoiceType
  external_number: string | null
  external_date: string | null
  customer: string
  company: string
  payment_method: string
  work_order_code: string | null
  quote_code: string | null
  net_amount: string
  vat_amount: string
  total_amount: string
  collected_amount: string
  residual_amount: string
  payment_status: InvoicePaymentStatus
  tag: InvoiceTag | null
  deviation: string | null
  last_reminder_at: string | null
  actions: InvoiceRowAction[]
}

/** `meta.aggregates` of the filtered `invoices` query (footer totals). */
export interface InvoiceAggregates {
  net_amount: number
  vat_amount: number
  total_amount: number
  collected_amount: number
  residual_amount: number
}
