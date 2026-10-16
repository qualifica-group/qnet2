import type { ScopeFilters } from '@/features/management-control/types'

/* Spec 0271: GET /api/management-control/account-flows. */

export const FINANCIAL_ACCOUNT_KINDS = ['bank_account', 'card', 'cash'] as const
export type FinancialAccountKind = (typeof FINANCIAL_ACCOUNT_KINDS)[number]

export interface AccountRow {
  /** Null when the document has no financial account ("unattributed"). */
  account: { id: number; name: string; type: FinancialAccountKind } | null
  amount: number
  installments: number
}

export interface AccountFlowSide {
  total: number
  installments: number
  by_account: AccountRow[]
}

export interface AccountMonthRow {
  /** `YYYY-MM`. */
  month: string
  collected: number
  paid: number
  net: number
}

export interface AccountFlows {
  applied: {
    date_from: string | null
    date_to: string | null
    company_id: number[]
    company_site_id: number[]
    operational_site_id: number[]
  }
  collections: AccountFlowSide
  payments: AccountFlowSide
  /** Every month of the period, in order. */
  by_month: AccountMonthRow[]
}

/** Filter state of the accounts page: period and scope, as the other pages of the module. */
export type AccountFlowsFilters = ScopeFilters

/** Request query parameters of the account flows, named exactly as the contract. */
export interface AccountFlowsParams {
  date_from?: string
  date_to?: string
  company_id?: number[]
  company_site_id?: number[]
  operational_site_id?: number[]
}
