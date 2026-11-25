/** Table-framework domain key of the payroll costs grid (spec 0430 D-11). */
export const PAYROLL_COSTS_DOMAIN = 'payroll-costs'

export interface PayrollImportSummary {
  rows: number
  people: number
  companies: number
  total_cost: number
  replaced_rows: number
}

export interface PayrollImportError {
  /** File row number, header = 1. */
  row: number
  column: string | null
  message: string
}

/** Envelope `data` of POST /payroll-costs/import (spec 0430 data_contract). */
export interface PayrollImportResult {
  applied: boolean
  /** "YYYY-MM"; null when the rows do not share a single month. */
  period: string | null
  summary: PayrollImportSummary
  errors: PayrollImportError[]
}
