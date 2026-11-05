import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchChartTree } from '@/features/chart-of-accounts/api'
import { chartOfAccountsKeys } from '@/features/chart-of-accounts/query-keys'
import type { ChartTree } from '@/features/chart-of-accounts/types'

/** The whole plan in one response (spec 0348 D-12): search and expansion are client-side. */
export function useChartTree() {
  return useQuery<ChartTree, AxiosError>({
    queryKey: chartOfAccountsKeys.tree(),
    queryFn: fetchChartTree,
  })
}
