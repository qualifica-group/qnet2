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
