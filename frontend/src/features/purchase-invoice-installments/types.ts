import type { NamedRef, ResidualMode } from '@/features/invoices/types'

/** Derived payable installment status (spec 0211 D-4): paid_amount > 0 means paid. */
export const PURCHASE_INSTALLMENT_STATUSES = ['unpaid', 'paid'] as const
export type PurchaseInstallmentStatus = (typeof PURCHASE_INSTALLMENT_STATUSES)[number]

/** Passive document kinds (spec 0211 D-1). */
export const PURCHASE_INVOICE_TYPES = ['proforma', 'invoice'] as const
export type PurchaseInvoiceType = (typeof PURCHASE_INVOICE_TYPES)[number]

/** Quick filter of the toolbar: to pay (default), due, overdue, paid, or every installment. */
export const PURCHASE_QUICK_FILTERS = ['unpaid', 'due', 'overdue', 'paid', 'all'] as const
export type PurchaseQuickFilter = (typeof PURCHASE_QUICK_FILTERS)[number]

/** Per-field permission block of the show endpoint (`field_permissions`). */
export interface PurchaseFieldPermission {
  visible: boolean
  editable: boolean
  required: boolean
}

/** Fields the PATCH can change; the only ones the edit dialog renders. */
export type EditablePurchaseInstallmentField = 'due_date' | 'payment_method_code'

/** One sibling installment of the document, as listed by the show endpoint. */
export interface PurchaseDocumentInstallment {
  id: number
  sequence: number
  due_date: string
  amount?: string
  paid_amount?: string | null
  status: PurchaseInstallmentStatus
}

/** `data` of GET/PATCH `/purchase-invoice-installments/{id}` (spec 0211 data_contract). Hidden fields are omitted. */
export interface PurchaseInstallmentDetail {
  id: number
  sequence: number
  due_date: string
  amount?: string
  payment_method_code: string | null
  paid_amount?: string | null
  paid_at?: string | null
  status: PurchaseInstallmentStatus
  residual_amount?: string
  is_overdue: boolean
  days_overdue: number
  financial_account?: NamedRef | null
  purchase_invoice: {
    id: number
    document_type: PurchaseInvoiceType
    document_number: string | null
    document_date: string
    total_amount: string
    supplier: NamedRef
    company: NamedRef
    financial_account: NamedRef | null
    purchase_orders: { id: number; code: string }[]
    installments: PurchaseDocumentInstallment[]
  }
  field_permissions: Partial<Record<string, PurchaseFieldPermission>>
  abilities: {
    update: boolean
    pay: boolean
  }
}

/** PATCH body: only the fields the actor may edit are sent. */
export interface PurchaseInstallmentUpdatePayload {
  due_date?: string
  payment_method_code?: string | null
}

/** PUT `/purchase-invoice-installments/{id}/payment` body; `residual_*` only on a partial payment. */
export interface PurchasePaymentPayload {
  paid_amount: number
  paid_at: string
  financial_account_id: number | null
  residual_mode?: ResidualMode
  residual_due_date?: string
}

/** One row of POST `/purchase-invoice-installments/payments`. */
export interface BulkPaymentItem {
  installment_id: number
  paid_amount: number
}

export interface BulkPaymentPayload {
  paid_at: string
  financial_account_id: number | null
  items: BulkPaymentItem[]
}

/** `data` of POST `/purchase-invoice-installments/payments`. */
export interface BulkPaymentResult {
  paid_count: number
  residual_count: number
}
