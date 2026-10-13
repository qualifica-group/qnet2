/**
 * Commission accruals types. Source of truth: the frozen `data_contract` of
 * spec 0212 (`commission_accruals`, read-only ledger).
 */

/** Table/module domain key (SSRM domain and permission prefix). */
export const COMMISSION_ACCRUALS_DOMAIN = 'commission-accruals'

/** Quick filters: open (default) | in a statement (draft or issued) | settled externally | everything. */
export const ACCRUAL_QUICK_FILTERS = ['open', 'in_statement', 'settled_externally', 'all'] as const
export type AccrualQuickFilter = (typeof ACCRUAL_QUICK_FILTERS)[number]
