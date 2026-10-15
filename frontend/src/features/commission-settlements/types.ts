/**
 * Commission settlements types. Source of truth: the frozen `data_contract`
 * of spec 0213 (`/commission-settlements`, `/commission-adjustments`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CommissionRecipientRole, NamedRef } from '@/features/commission-settlement-runs/types'
import type { InvoicePaymentStatus } from '@/features/invoices/types'

/** Table/module domain key (SSRM domain and permission prefix). */
export const COMMISSION_SETTLEMENTS_DOMAIN = 'commission-settlements'

export const SETTLEMENT_STATUSES = ['DRAFT', 'ISSUED', 'DOCUMENT_RECEIVED', 'APPROVED', 'CANCELLED'] as const
export type SettlementStatus = (typeof SETTLEMENT_STATUSES)[number]

/** Quick filters of the list: drafts (default) | issued | document received | approved | cancelled | everything. */
export const SETTLEMENT_QUICK_FILTERS = ['draft', 'issued', 'document_received', 'approved', 'cancelled', 'all'] as const
export type SettlementQuickFilter = (typeof SETTLEMENT_QUICK_FILTERS)[number]

export type MovementType = 'ACCRUAL' | 'REVERSAL' | 'ADJUSTMENT'

export interface CodedRef {
  id: number
  code: string
}

export interface SettlementMovement {
  id: number
  type: MovementType
  recipient_role: CommissionRecipientRole | null
  accrued_on: string
  /** Absent when the actor cannot see commission-accruals amounts. */
  amount?: string
  base_amount?: string | null
  notes: string | null
  invoice: CodedRef | null
  quote: CodedRef | null
  product: string | null
  can_edit: boolean
}

/** Per-record action availability computed by the server. */
export interface SettlementAbilities {
  recalculate: boolean
  issue: boolean
  cancel: boolean
  delete: boolean
  adjust: boolean
  update: boolean
  receive_document: boolean
  remove_document: boolean
  approve: boolean
}

/** Document of the agent (spec 0267 D-3); the amounts and `differences` (document minus statement, signed) follow the statement's amount permissions. */
export interface SettlementDocument {
  number: string
  date: string
  taxable_amount?: string
  vat_amount?: string
  withholding_amount?: string
  enasarco_amount?: string
  total_amount?: string
  received_at: string
  received_by: NamedRef | null
  differences?: DocumentDifferences
}

export type DocumentAmountKey = 'taxable_amount' | 'vat_amount' | 'withholding_amount' | 'enasarco_amount' | 'total_amount'
export type DocumentDifferences = Record<DocumentAmountKey, string>

/** The passive invoice created by the approval; its payment status is the settlement's (never stored on it). */
export interface SettlementPurchaseInvoice {
  id: number
  document_number: string
  document_date: string
  payable_amount?: string
  paid_amount?: string
  residual_amount?: string
  payment_status: InvoicePaymentStatus
}

/** Optional amounts (`?`) are hidden by field permissions and absent from the payload (accruals and adjustments too: their sum would reveal the taxable amount). */
export interface SettlementDetail {
  id: number
  /** "n/year" once issued, null for drafts. */
  code: string | null
  status: SettlementStatus
  period_from: string
  period_to: string
  run_id: number | null
  company: NamedRef
  referent: NamedRef
  agent_fiscal_profile_id: number
  accruals_amount?: string
  adjustments_amount?: string
  taxable_amount?: string
  vat_rate: string | null
  vat_amount?: string
  withholding_enabled: boolean
  withholding_rate: string | null
  withholding_base_percent: string | null
  withholding_amount?: string
  enasarco_enabled: boolean
  enasarco_mandate_type: 'mono' | 'pluri' | null
  enasarco_base_amount?: string
  enasarco_agent_amount?: string
  enasarco_principal_amount?: string
  net_payable?: string
  notes: string | null
  issued_at: string | null
  issued_by: NamedRef | null
  cancelled_at: string | null
  cancelled_by: NamedRef | null
  cancellation_reason: string | null
  document: SettlementDocument | null
  approved_at: string | null
  approved_by: NamedRef | null
  purchase_invoice: SettlementPurchaseInvoice | null
  /** Registry linked to the agent as supplier (`referents.supplier_registry_id`). */
  supplier: NamedRef | null
  created_at: string
  movements: SettlementMovement[]
  abilities: SettlementAbilities
}

export interface SettlementDetailWithPermissions extends SettlementDetail {
  permissions: ResourcePermissions
}

/** Body of PATCH /commission-settlements/{id} (DRAFT only). */
export interface SettlementNotesPayload {
  notes: string | null
}

/** Body of POST /commission-settlements/{id}/cancel. */
export interface SettlementCancelPayload {
  reason: string
}

/** `data` of the adjustment endpoints. */
export interface CommissionAdjustment {
  id: number
  type: 'ADJUSTMENT'
  referent: NamedRef
  company: NamedRef
  amount: string
  accrued_on: string
  notes: string
  commission_settlement_id: number | null
}

/** Body of POST /commission-adjustments. */
export interface AdjustmentCreatePayload {
  referent_id: number
  company_id: number
  amount: number
  accrued_on: string
  notes: string
  commission_settlement_id: number | null
}

/** Body of PUT /commission-adjustments/{id}. */
export interface AdjustmentUpdatePayload {
  amount: number
  accrued_on: string
  notes: string
}

/**
 * How the adjustment dialog is used: standalone (pick agent + company), inside
 * a draft (agent/company fixed, sends the settlement id) or editing a movement.
 */
export type AdjustmentMode =
  | { type: 'standalone' }
  | { type: 'draft'; settlementId: number; referent: NamedRef; company: NamedRef }
  | { type: 'edit'; adjustmentId: number; amount: string; accruedOn: string; notes: string }

/** Body of POST /commission-settlements/{id}/document. */
export interface SettlementDocumentPayload {
  document_number: string
  document_date: string
  document_taxable_amount: number
  document_vat_amount: number
  document_withholding_amount: number
  document_enasarco_amount: number
  document_total_amount: number
}

export interface SupplierCandidate {
  id: number
  name: string
  tax_code: string | null
  vat_number: string | null
  /** A registry that is not a supplier cannot be linked (422 on `registry_id`). */
  is_supplier: boolean
}

/** `data` of GET /commission-settlements/{id}/approval-draft. */
export interface ApprovalDraft {
  supplier: NamedRef | null
  /** Only when `supplier` is null. */
  supplier_candidates: SupplierCandidate[]
  can_create_supplier: boolean
  payment_method_id: number | null
  cost_type_id: number | null
  vat_rate_id: number | null
  line_description: string
}

/** Body of POST /commission-settlements/{id}/supplier: an id links, `null` creates from the referent. */
export interface SettlementSupplierPayload {
  registry_id: number | null
}

/** Body of POST /commission-settlements/{id}/approve. */
export interface SettlementApprovePayload {
  payment_method_id: number
  cost_type_id: number
  vat_rate_id: number
  financial_account_id: number | null
}
