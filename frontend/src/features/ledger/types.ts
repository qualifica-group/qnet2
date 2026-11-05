import type { InstallmentCompensationRef } from '@/features/compensations/types'
import type { WriteOffReason } from '@/features/invoices/types'

/** Frozen contract of `GET /api/registries/{registry}/ledger` (spec 0266, write-off rows spec 0361). */

export type LedgerDocumentType = 'all' | 'proforma' | 'invoice'
export type LedgerStatus = 'paid' | 'not_due' | 'overdue' | 'seriously_overdue'
export type LedgerSide = 'customer' | 'supplier'

export interface LedgerNamedRef {
  id: number
  name: string
}

export interface LedgerInstallment {
  id: number
  sequence: number
  due_date: string
  amount: number
  settled_amount: number | null
  settled_at: string | null
  residual_amount: number
  days_late: number
  write_off_amount: number | null
  /** Spec 0364: set when the installment was settled by a compensation. */
  compensation: InstallmentCompensationRef | null
}

export interface LedgerDocument {
  id: number
  /** `write_off`: a synthetic row after the document; its `id` is the installment id and may equal a document id. */
  document_type: 'proforma' | 'invoice' | 'write_off'
  number_label: string | null
  external_number: string | null
  document_date: string
  company: LedgerNamedRef | null
  company_site: LedgerNamedRef | null
  operational_site: LedgerNamedRef | null
  debit: number
  credit: number
  settled_amount: number
  residual_amount: number
  running_balance: number
  status: LedgerStatus
  installments: LedgerInstallment[]
  write_off_reason?: WriteOffReason
}

export interface LedgerAging {
  overdue_1_30: number
  overdue_31_60: number
  overdue_61_90: number
  overdue_over_90: number
  due_0_30: number
  due_31_60: number
  due_over_60: number
}

export interface LedgerSummary {
  invoiced_total: number
  settled_total: number
  write_off_total: number
  /** Spec 0364: total settled by compensations. */
  compensated_total: number
  residual_total: number
  overdue_total: number
  not_due_total: number
  aging: LedgerAging
}

export interface LedgerSection {
  opening_balance: number
  summary: LedgerSummary
  documents: LedgerDocument[]
}

export interface Ledger {
  registry: { id: number; name: string; is_supplier: boolean }
  today: string
  customer: LedgerSection | null
  supplier: LedgerSection | null
}

/** Filter state of the panel (UI side; mapped to the request by `toLedgerParams`). */
export interface LedgerFilters {
  documentType: LedgerDocumentType
  dateFrom: string
  dateTo: string
  companyIds: number[]
  companySiteIds: number[]
  operationalSiteIds: number[]
}

/** Request query parameters, named exactly as the contract. */
export interface LedgerParams {
  document_type: LedgerDocumentType
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
}
