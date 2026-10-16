import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchMonthlyTrend, managementControlKeys, toTrendParams } from '@/features/management-control/api'
import type { MonthlyTrend, TrendFilters } from '@/features/management-control/trend-types'

/** Monthly trend for the given filters; waits for both ends of the period (the server requires them). */
export function useMonthlyTrend(filters: TrendFilters) {
  const params = useMemo(() => toTrendParams(filters), [filters])

  return useQuery<MonthlyTrend, AxiosError>({
    queryKey: managementControlKeys.monthlyTrend(params),
    queryFn: () => fetchMonthlyTrend(params),
    enabled: Boolean(params.date_from && params.date_to),
  })
}
