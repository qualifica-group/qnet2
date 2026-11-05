/** Account statement types. Source of truth: spec 0355 frozen `data_contract`. */

export const STATEMENT_TYPES = [
  'manual_income',
  'manual_expense',
  'transfer',
  'adjustment',
  'collection',
  'payment',
] as const
export type StatementType = (typeof STATEMENT_TYPES)[number]

export interface AccountCompany {
  id: number
  name: string
}

export interface StatementAccountRef {
  id: number
  name: string
  type?: string
  company: AccountCompany | null
}

export interface AccountBalance extends StatementAccountRef {
  balance: string
}

export interface AccountBalances {
  date: string
  accounts: AccountBalance[]
  total: string
}

export interface StatementDocument {
  kind: 'invoice' | 'purchase_invoice'
  id: number
  number: string | null
}

export interface StatementRow {
  key: string
  date: string
  source: 'movement' | 'collection' | 'payment'
  type: string
  description: string
  registry: { id: number; name: string } | null
  document: StatementDocument | null
  work_order: { id: number; code: string } | null
  inflow: string | null
  outflow: string | null
  balance: string
  movement_id: number | null
}

export interface Statement {
  account: StatementAccountRef
  opening: { date: string; balance: string }
  period_opening_balance: string
  rows: StatementRow[]
  totals: { inflow: string; outflow: string }
  closing_balance: string
  balance_today: string
  filtered: boolean
}

export interface UnassignedRow {
  source: 'collection' | 'payment'
  installment_id: number
  document: StatementDocument
  registry: { id: number; name: string } | null
  date: string
  amount: string
}

export interface UnassignedList {
  count: number
  rows: UnassignedRow[]
}

/** UI filters of the statement page. */
export interface StatementFilters {
  dateFrom: string
  dateTo: string
  types: StatementType[]
  registryId: number | null
  search: string
}

/** Query parameters of statement and export, as sent to the backend. */
export interface StatementParams {
  date_from?: string
  date_to?: string
  types?: StatementType[]
  registry_id?: number
  search?: string
}
