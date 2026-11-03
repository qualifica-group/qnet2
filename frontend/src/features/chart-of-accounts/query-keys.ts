import { CHART_ACCOUNTS_DOMAIN } from '@/features/chart-of-accounts/types'

/** Centralized query keys of the chart-of-accounts feature. */
export const chartOfAccountsKeys = {
  all: [CHART_ACCOUNTS_DOMAIN] as const,
  tree: () => [CHART_ACCOUNTS_DOMAIN, 'tree'] as const,
}
