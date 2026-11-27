import type { ExposureDocumentType } from '@/features/management-control/exposure-types'
import type { ScopeSelection } from '@/features/management-control/types'

/* Spec 0270: GET /api/management-control/cash-flow. */

export const CASH_FLOW_HORIZONS = [3, 6, 12, 24] as const
export type CashFlowHorizon = (typeof CASH_FLOW_HORIZONS)[number]

/**
 * Amounts on the document total; `net` = receivable - payable - committed - salaries - f24.
 * `salaries` and `f24` are estimated personnel outflows (spec 0434), zero when excluded.
 */
export interface Flow {
  receivable: number
  payable: number
  committed: number
  salaries: number
  f24: number
  net: number
}

export interface CashFlowMonth extends Flow {
  /** `YYYY-MM`. */
  month: string
  /** Running sum of the monthly net up to this month, backlog excluded. */
  cumulative: number
}

export interface CompanyMonthNet {
  month: string
  net: number
}

export interface CompanyFlowRow {
  key: string
  label: string | null
  backlog_net: number
  months: CompanyMonthNet[]
  beyond_net: number
  total_net: number
}

export interface CashFlow {
  applied: {
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
    document_type: string
    horizon_months: number
    include_personnel: boolean
  }
  today: string
  backlog: Flow
  months: CashFlowMonth[]
  beyond: Flow
  by_company: CompanyFlowRow[]
}

/** Filter state of the cash flow page (UI side; mapped to the request by `toCashFlowParams`). */
export interface CashFlowFilters extends ScopeSelection {
  documentType: ExposureDocumentType
  horizonMonths: CashFlowHorizon
  /** Include the estimated personnel outflows (default on). */
  includePersonnel: boolean
}

/** Request query parameters of the cash flow, named exactly as the contract. */
export interface CashFlowParams {
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
  document_type: ExposureDocumentType
  horizon_months: CashFlowHorizon
  /** Sent only when off: the server default is on. */
  include_personnel?: false
}
