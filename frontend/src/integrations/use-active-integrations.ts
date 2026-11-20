import { useQuery } from '@tanstack/react-query'
import { fetchActiveIntegrations } from '@/integrations/api'
import { integrationKeys } from '@/integrations/query-keys'

/** The set changes only with a deploy/.env change: no need to refetch it often. */
const ACTIVE_INTEGRATIONS_STALE_MS = 10 * 60 * 1000

const NONE: string[] = []

export function useActiveIntegrations() {
  const query = useQuery({
    queryKey: integrationKeys.active,
    queryFn: fetchActiveIntegrations,
    staleTime: ACTIVE_INTEGRATIONS_STALE_MS,
    retry: false,
  })

  return { active: query.data?.active ?? NONE, isLoading: query.isLoading }
}

export function useIsIntegrationActive(key: string) {
  const { active, isLoading } = useActiveIntegrations()
  return { isActive: active.includes(key), isLoading }
}
