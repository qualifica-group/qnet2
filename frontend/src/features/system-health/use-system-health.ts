import { useQuery } from '@tanstack/react-query'
import { fetchSystemHealth } from '@/features/system-health/api'
import { systemHealthKeys } from '@/features/system-health/query-keys'
import type { SystemHealthData } from '@/features/system-health/types'

/** Auto-refresh interval of the live health snapshot (spec 0187, AC-014). */
export const SYSTEM_HEALTH_REFETCH_MS = 30000

export function useSystemHealth() {
  return useQuery<SystemHealthData>({
    queryKey: systemHealthKeys.all,
    queryFn: fetchSystemHealth,
    refetchInterval: SYSTEM_HEALTH_REFETCH_MS,
  })
}
