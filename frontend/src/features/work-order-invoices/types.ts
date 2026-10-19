import type { InvoicePaymentStatus, InvoiceType } from '@/features/invoices/types'
import type { PurchaseInvoiceType } from '@/features/purchase-invoice-installments/types'

/** Amounts are decimal strings (bcmath), as in the invoice resources. */
export interface WorkOrderInvoiceParty {
  id: number
  name: string
}

/** Customer proforma / invoice linked to the work order (spec 0282 data contract). */
export interface WorkOrderActiveDocument {
  id: number
  type: InvoiceType
  number_label: string
  document_date: string
  external_number: string | null
  external_date: string | null
  customer: WorkOrderInvoiceParty
  company: WorkOrderInvoiceParty
  net_amount: string
  vat_amount: string
  total_amount: string
  collected_amount: string
  residual_amount: string
  payment_status: InvoicePaymentStatus
}

/** Supplier proforma / invoice with at least one line on the work order. */
export interface WorkOrderPassiveDocument {
  id: number
  document_type: PurchaseInvoiceType
  document_number: string
  document_date: string
  supplier: WorkOrderInvoiceParty
  company: WorkOrderInvoiceParty
  total_amount: string
  /** Sum of the document lines placed on this work order. */
  work_order_amount: string
  paid_amount: string
  residual_amount: string
}

export interface WorkOrderActiveTotals {
  total_amount: string
  collected_amount: string
  residual_amount: string
}

export interface WorkOrderPassiveTotals {
  total_amount: string
  work_order_amount: string
  paid_amount: string
  residual_amount: string
}

export interface WorkOrderInvoices {
  active: WorkOrderActiveDocument[]
  passive: WorkOrderPassiveDocument[]
  totals: {
    active: WorkOrderActiveTotals
    passive: WorkOrderPassiveTotals
  }
}
