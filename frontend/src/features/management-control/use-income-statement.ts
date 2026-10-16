import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchIncomeStatement, managementControlKeys, toIncomeStatementParams } from '@/features/management-control/api'
import type { IncomeStatement, IncomeStatementFilters } from '@/features/management-control/types'

/** Income statement for the given filters; refetches when any filter changes. */
export function useIncomeStatement(filters: IncomeStatementFilters) {
  const params = useMemo(() => toIncomeStatementParams(filters), [filters])

  return useQuery<IncomeStatement, AxiosError>({
    queryKey: managementControlKeys.incomeStatement(params),
    queryFn: () => fetchIncomeStatement(params),
  })
}
