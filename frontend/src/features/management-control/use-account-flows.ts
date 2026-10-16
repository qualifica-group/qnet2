import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAccountFlows, managementControlKeys, toAccountFlowsParams } from '@/features/management-control/api'
import type { AccountFlows, AccountFlowsFilters } from '@/features/management-control/accounts-types'

/** Receipts and payments by financial account and month; waits for both ends of the period (the server requires them). */
export function useAccountFlows(filters: AccountFlowsFilters) {
  const params = useMemo(() => toAccountFlowsParams(filters), [filters])

  return useQuery<AccountFlows, AxiosError>({
    queryKey: managementControlKeys.accountFlows(params),
    queryFn: () => fetchAccountFlows(params),
    enabled: Boolean(params.date_from && params.date_to),
  })
}
