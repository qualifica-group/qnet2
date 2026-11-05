/** Targets of the drill-down links (spec 0362, AC-012): each figure opens the list it comes from. */
export const OVERVIEW_LINKS = {
  statements: '/account-statements',
  receivable: '/invoice-installments',
  payable: '/purchase-invoice-installments',
  movements: '/treasury-movements',
} as const

export function accountStatementLink(accountId: number): string {
  return `${OVERVIEW_LINKS.statements}?account=${accountId}`
}

/** Installment grid opened on one of its quick filters (spec 0363 `?quick=`): the grid applies it at mount. */
export function installmentQuickLink(base: string, quick: 'unpaid' | 'due' | 'overdue'): string {
  return `${base}?quick=${quick}`
}

/** Statement of one account showing only the rows not yet ticked against the bank statement. */
export function unreconciledStatementLink(accountId: number): string {
  return `${accountStatementLink(accountId)}&reconciled=no`
}
