/**
 * Commission settlement runs types. Source of truth: the frozen
 * `data_contract` of spec 0213 (`/commission-settlement-runs`).
 */

/** Table domain key (SSRM domain and ability prefix of the table endpoint). */
export const COMMISSION_SETTLEMENT_RUNS_DOMAIN = 'commission-settlement-runs'

export const COMMISSION_RECIPIENT_ROLES = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR'] as const
export type CommissionRecipientRole = (typeof COMMISSION_RECIPIENT_ROLES)[number]

export const SETTLEMENT_SKIP_REASONS = [
  'draft_exists',
  'non_positive',
  'below_threshold',
  'missing_profile',
  'missing_enasarco_parameters',
] as const
export type SettlementSkipReason = (typeof SETTLEMENT_SKIP_REASONS)[number]

export interface NamedRef {
  id: number
  name: string
}

/** Body of POST /commission-settlement-runs. */
export interface CommissionSettlementRunPayload {
  period_from: string
  period_to: string
  company_id: number | null
  referent_ids: number[] | null
  recipient_roles: CommissionRecipientRole[] | null
  min_amount: number | null
}

export interface CommissionSettlementRunSummary {
  id: number
  period_from: string
  period_to: string
  filters: Record<string, unknown> | null
  min_amount: string
  settlements_count: number
  created_at: string
  created_by: NamedRef
}

export interface CreatedSettlement {
  settlement_id: number
  referent: NamedRef
  company: NamedRef
  taxable_amount: string
  net_payable: string
}

export interface SkippedSettlement {
  referent: NamedRef
  company: NamedRef
  reason: SettlementSkipReason
  taxable_amount: string
}

/** `data` of POST (201) and GET /commission-settlement-runs/{id}. */
export interface CommissionSettlementRunResult {
  run: CommissionSettlementRunSummary
  created: CreatedSettlement[]
  skipped: SkippedSettlement[]
}
