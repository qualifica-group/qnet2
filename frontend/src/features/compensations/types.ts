/** Frozen contract of spec 0364 (installment compensation). Amounts are decimal strings, dates `YYYY-MM-DD`. */

export const COMPENSATION_RESIDUAL_MODES = ['spread', 'new_installment'] as const
export type CompensationResidualMode = (typeof COMPENSATION_RESIDUAL_MODES)[number]

export interface ReceivableCandidate {
  id: number
  sequence: number
  due_date: string
  residual: string
  invoice: { id: number; number_label: string; date: string }
}

export interface PayableCandidate {
  id: number
  sequence: number
  due_date: string
  residual: string
  purchase_invoice: { id: number; number_label: string; date: string }
}

export interface CompensationCandidates {
  receivables: ReceivableCandidate[]
  payables: PayableCandidate[]
}

/** One row of `GET /registries/{id}/compensations` (and `data` of the POST). */
export interface InstallmentCompensation {
  id: number
  company: { id: number; name: string }
  compensated_at: string
  amount: string
  note: string | null
  invoice_installment: { id: number; sequence: number; invoice: { id: number; number_label: string } }
  purchase_invoice_installment: { id: number; sequence: number; purchase_invoice: { id: number; number_label: string } }
  created_by: { id: number; name: string } | null
  created_at: string
  can_delete: boolean
}

/** `compensation` field of the installment resources and of the ledger installments. */
export interface InstallmentCompensationRef {
  id: number
  amount: string | number
  counterpart_label: string
}

export interface CompensationPayload {
  invoice_installment_id: number
  purchase_invoice_installment_id: number
  compensated_at: string
  note?: string | null
  residual_mode?: CompensationResidualMode
  residual_due_date?: string
}

/** What opens the dialog: the registry, and optionally the company and the installment already chosen. */
export interface CompensationTarget {
  registryId: number
  companyId?: number
  receivableId?: number
  payableId?: number
}
