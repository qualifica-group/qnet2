import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchCashFlow, managementControlKeys, toCashFlowParams } from '@/features/management-control/api'
import type { CashFlow, CashFlowFilters } from '@/features/management-control/cash-flow-types'

/** Cash flow forecast for the given filters; refetches when any filter changes. */
export function useCashFlow(filters: CashFlowFilters) {
  const params = useMemo(() => toCashFlowParams(filters), [filters])

  return useQuery<CashFlow, AxiosError>({
    queryKey: managementControlKeys.cashFlow(params),
    queryFn: () => fetchCashFlow(params),
  })
}
