/**
 * Commission settlements types. Source of truth: the frozen `data_contract`
 * of spec 0213 (`/commission-settlements`, `/commission-adjustments`).
 */

import type { ResourcePermissions } from '@/features/authorization/types'
import type { CommissionRecipientRole, NamedRef } from '@/features/commission-settlement-runs/types'

/** Table/module domain key (SSRM domain and permission prefix). */
export const COMMISSION_SETTLEMENTS_DOMAIN = 'commission-settlements'

export const SETTLEMENT_STATUSES = ['DRAFT', 'ISSUED', 'CANCELLED'] as const
export type SettlementStatus = (typeof SETTLEMENT_STATUSES)[number]

/** Quick filters of the list: drafts (default) | issued | cancelled | everything. */
export const SETTLEMENT_QUICK_FILTERS = ['draft', 'issued', 'cancelled', 'all'] as const
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
