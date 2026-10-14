/**
 * Passive invoices (spec 0250 data_contract). Money amounts coming from the
 * server are decimal STRINGS ("1234.50"); payload amounts are numbers.
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { BankAccountRef, NamedRef, VatRateRef } from '@/features/invoices/types'
import type { PurchaseInstallmentStatus, PurchaseInvoiceType } from '@/features/purchase-invoice-installments/types'

export type { PurchaseInvoiceType }
export { PURCHASE_INVOICE_TYPES } from '@/features/purchase-invoice-installments/types'

export interface CostTypeRef extends NamedRef {
  color: string | null
}

export interface WorkOrderRef {
  id: number
  code: string
  title: string | null
}

export interface PurchaseOrderRef {
  id: number
  code: string
}

/** One row of a passive document (detail and draft share this shape; the draft has no persisted id). */
export interface PurchaseInvoiceLine {
  id: number
  purchase_order_line_id: number | null
  purchase_order: PurchaseOrderRef | null
  product: NamedRef | null
  description: string
  /** Decimal string with 3 decimals. */
  quantity: string
  unit_price: string
  vat_rate: VatRateRef | null
  cost_type: CostTypeRef
  work_order: WorkOrderRef | null
  net_amount: string
  vat_amount: string
  total_amount: string
  sort_order: number
  /** Billable remainder (3 decimals); only on draft rows that come from an ODA row (spec 0254). */
  available_quantity?: string
}

/** Installment of the document, as `PurchaseInvoiceInstallmentResource` (gated fields may be absent). */
export interface PurchaseInvoiceInstallment {
  id: number
  sequence: number
  due_date: string
  amount?: string
  payment_method_code: string | null
  paid_amount?: string | null
  paid_at?: string | null
  status: PurchaseInstallmentStatus
  residual_amount?: string
}

/** `data` of GET/POST/PUT/PATCH purchase invoice (permissions come beside it in the envelope). */
export interface PurchaseInvoice {
  id: number
  document_type: PurchaseInvoiceType
  document_number: string | null
  document_date: string
  company: NamedRef
  supplier: NamedRef
  payment_method: NamedRef
  financial_account: BankAccountRef | null
  company_site: NamedRef | null
  operational_site: NamedRef | null
  /** Default proposed to new rows (no longer the source of the datum). */
  cost_type: CostTypeRef | null
  work_order: WorkOrderRef | null
  notes: string | null
  net_amount: string
  vat_amount: string
  total_amount: string
  paid_amount: string
  residual_amount: string
  has_payments: boolean
  purchase_orders: PurchaseOrderRef[]
  lines: PurchaseInvoiceLine[]
  installments: PurchaseInvoiceInstallment[]
  created_by: NamedRef
  created_at: string
  permissions: ResourcePermissions
}

/** Row of the write payload. `id` keeps an existing row on update. */
export interface PurchaseInvoiceLineWrite {
  id?: number
  purchase_order_line_id?: number | null
  product_id?: number | null
  description: string
  quantity: number
  unit_price: number
  vat_rate_id?: number | null
  cost_type_id: number
  work_order_id?: number | null
}

/** POST body; PUT takes the same without `document_type`/`document_number`. */
export interface PurchaseInvoiceWritePayload {
  document_type?: PurchaseInvoiceType
  document_number?: string | null
  document_date: string
  company_id: number
  supplier_id: number
  payment_method_id: number
  financial_account_id?: number | null
  company_site_id?: number | null
  operational_site_id?: number | null
  cost_type_id?: number | null
  work_order_id?: number | null
  notes?: string | null
  purchase_order_ids?: number[]
  lines: PurchaseInvoiceLineWrite[]
}

/** PATCH details body: a filled number makes the document an invoice, an empty one a proforma. */
export interface PurchaseInvoiceDetailsPayload {
  document_number: string | null
  notes?: string | null
}

/** Draft row: the persisted line projection with a null id (not saved yet). */
export type PurchaseInvoiceDraftLine = Omit<PurchaseInvoiceLine, 'id'> & { id: null }

/** `data` of GET /purchase-invoices/draft. */
export interface PurchaseInvoiceDraft {
  defaults: {
    company: NamedRef
    supplier: NamedRef
    payment_method: NamedRef | null
    company_site: NamedRef | null
    operational_site: NamedRef | null
    cost_type: CostTypeRef | null
    work_order: WorkOrderRef | null
  }
  lines: PurchaseInvoiceDraftLine[]
  purchase_order_ids: number[]
}

/** `data` row of GET /purchase-invoices/invoiceable-orders. */
export interface InvoiceableOrder {
  id: number
  code: string
  order_date: string
  grand_total: string
  status: string
}

export interface PurchaseInstallmentPreviewPayload {
  purchase_invoice_id?: number | null
  document_date: string
  payment_method_id: number
  net_amount: number
  vat_amount: number
  total_amount: number
}

/** Plan row of the installment preview; `locked`/`paid_amount` only appear on a rebalanced paid row. */
export interface PurchaseInstallmentPreviewRow {
  sequence: number
  due_date: string
  amount: string
  payment_method_code: string | null
  paid_amount?: string | null
  locked?: boolean
}
