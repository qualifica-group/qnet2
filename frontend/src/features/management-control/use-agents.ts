import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchAgents, managementControlKeys, toAgentsParams } from '@/features/management-control/api'
import type { Agents, AgentsFilters } from '@/features/management-control/agents-types'

/** Commissions by agent and month; waits for both ends of the period (the server requires them). */
export function useAgents(filters: AgentsFilters) {
  const params = useMemo(() => toAgentsParams(filters), [filters])

  return useQuery<Agents, AxiosError>({
    queryKey: managementControlKeys.agents(params),
    queryFn: () => fetchAgents(params),
    enabled: Boolean(params.date_from && params.date_to),
  })
}
