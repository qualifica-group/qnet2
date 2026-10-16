import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchStructureCosts, managementControlKeys, toStructureCostsParams } from '@/features/management-control/api'
import type { IncomeStatementFilters, StructureCosts, StructureGroupBy } from '@/features/management-control/types'

/** Structure costs for the shared page filters and the panel's own grouping. */
export function useStructureCosts(filters: IncomeStatementFilters, groupBy: StructureGroupBy) {
  const params = useMemo(() => toStructureCostsParams(filters, groupBy), [filters, groupBy])

  return useQuery<StructureCosts, AxiosError>({
    queryKey: managementControlKeys.structureCosts(params),
    queryFn: () => fetchStructureCosts(params),
  })
}
