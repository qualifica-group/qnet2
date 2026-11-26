export interface PayslipImportSummary {
  rows: number
  people: number
  companies: number
  total_net: number
  created: number
  updated: number
  suppliers_created: number
  suppliers_linked: number
}

/** A blocking error (errors) or a non-blocking notice (warnings) of one file row. */
export interface PayslipImportIssue {
  /** File row number, header = 1. */
  row: number
  column: string | null
  message: string
}

/** Envelope `data` of POST /purchase-invoices/payslips/import (spec 0431 data_contract). */
export interface PayslipImportResult {
  applied: boolean
  /** "YYYY-MM". */
  period: string | null
  summary: PayslipImportSummary
  errors: PayslipImportIssue[]
  warnings: PayslipImportIssue[]
}

/** What both import steps send: the file and the payment date of the single installment of each payslip. */
export interface PayslipImportRequest {
  file: File
  paymentDate: string
}
