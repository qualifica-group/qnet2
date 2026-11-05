/** Account statement types. Source of truth: spec 0360 frozen `data_contract`. */

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
  /** Spec 0363: the row has been ticked against the bank statement. */
  reconciled: boolean
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
  /** Spec 0363 D-5: opening balance + ticked rows up to `date_to`; independent of the filters. */
  reconciled_balance: string
  /** Rows not ticked from the opening date to `date_to`; independent of the filters. */
  unreconciled_count: number
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

/** Reconciled filter of the statement: every row, only ticked rows (`yes`) or only unticked ones (`no`). */
export const RECONCILED_FILTERS = ['all', 'yes', 'no'] as const
export type ReconciledFilter = (typeof RECONCILED_FILTERS)[number]

/** UI filters of the statement page. */
export interface StatementFilters {
  dateFrom: string
  dateTo: string
  types: StatementType[]
  registryId: number | null
  search: string
  reconciled: ReconciledFilter
}

/** Query parameters of statement and export, as sent to the backend. */
export interface StatementParams {
  date_from?: string
  date_to?: string
  types?: StatementType[]
  registry_id?: number
  search?: string
  reconciled?: 'yes' | 'no'
}

/** One saved bank reconciliation (spec 0363). */
export interface BankReconciliation {
  id: number
  statement_date: string
  bank_balance: string
  reconciled_balance: string
  /** bank_balance - reconciled_balance at save time, before the adjustment. */
  difference: string
  adjustment_movement_id: number | null
  note: string | null
  created_by: { id: number; name: string } | null
  created_at: string
}

export interface ReconciliationPreview {
  statement_date: string
  reconciled_balance: string
  unreconciled_count: number
}

export interface ReconciliationPayload {
  statement_date: string
  bank_balance: string
  note: string | null
  create_adjustment: boolean
  treasury_type_id?: number
}
