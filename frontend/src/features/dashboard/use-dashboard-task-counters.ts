import { useQuery } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import { fetchDashboardTaskCounters } from '@/features/dashboard/api'
import { dashboardKeys } from '@/features/dashboard/query-keys'
import type { DashboardTaskCounters } from '@/features/dashboard/types'

/**
 * Server state of the "Attività da completare" cards (spec 0151 AC-009).
 * `enabled` mirrors the `tasks.viewAny` gate: the section that mounts this
 * hook is itself only rendered when the permission is granted, so no request
 * is ever issued for a hidden block (AC-011).
 */
export function useDashboardTaskCounters(enabled = true) {
  return useQuery<DashboardTaskCounters, AxiosError>({
    queryKey: dashboardKeys.tasks,
    queryFn: fetchDashboardTaskCounters,
    enabled,
  })
}
