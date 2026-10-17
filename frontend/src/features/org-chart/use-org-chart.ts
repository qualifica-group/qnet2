import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchOrgChartFunctions, fetchOrgChartPeople, orgChartKeys } from '@/features/org-chart/api'
import type { OrgChartFunctions, OrgDirectoryPerson } from '@/features/org-chart/types'

export function useOrgChartFunctions(): UseQueryResult<OrgChartFunctions, AxiosError> {
  return useQuery<OrgChartFunctions, AxiosError>({
    queryKey: orgChartKeys.functions,
    queryFn: fetchOrgChartFunctions,
  })
}

export function useOrgChartPeople(): UseQueryResult<OrgDirectoryPerson[], AxiosError> {
  return useQuery<OrgDirectoryPerson[], AxiosError>({
    queryKey: orgChartKeys.people,
    queryFn: fetchOrgChartPeople,
  })
}
