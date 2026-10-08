import type { InstallmentStatus, NamedRef } from '@/features/invoices/types'

/** Quick filter of the toolbar (spec 0197 D-9): not collected (default), due, overdue, collected, or every installment. */
export const INSTALLMENT_QUICK_FILTERS = ['unpaid', 'due', 'overdue', 'paid', 'all'] as const
export type InstallmentQuickFilter = (typeof INSTALLMENT_QUICK_FILTERS)[number]

/** Per-field permission block of the show endpoint (`field_permissions`). */
export interface InstallmentFieldPermission {
  visible: boolean
  editable: boolean
  required: boolean
}

/** Fields the PATCH can change; the only ones the edit dialog renders. */
export const EDITABLE_INSTALLMENT_FIELDS = ['due_date', 'payment_method_code'] as const
export type EditableInstallmentField = (typeof EDITABLE_INSTALLMENT_FIELDS)[number]

/** `data` of GET/PATCH `/invoice-installments/{id}` (spec 0197 data_contract). Fields hidden by field permission are omitted. */
export interface InstallmentDetail {
  id: number
  sequence: number
  due_date: string
  amount?: string
  payment_method_code: string | null
  collected_amount?: string | null
  collected_at?: string | null
  status: InstallmentStatus
  residual_amount?: string
  is_overdue: boolean
  days_overdue: number
  invoice: {
    id: number
    number_label: string
    document_date: string
    total_amount: string
    customer: NamedRef
    work_order: { id: number; code: string; title: string } | null
    company_site: NamedRef | null
    operational_site: { id: number; alias: string } | null
  }
  field_permissions: Partial<Record<string, InstallmentFieldPermission>>
  abilities: {
    update: boolean
    collect: boolean
    view_invoice: boolean
  }
}

/** PATCH body: only the fields the actor may edit are sent. */
export interface InstallmentUpdatePayload {
  due_date?: string
  payment_method_code?: string | null
}
