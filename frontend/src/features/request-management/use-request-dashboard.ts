import { useCallback } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import {
  fetchRequestManagementDashboard,
  type RequestDashboardQuery,
} from '@/features/request-management/dashboard-api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { useRequestModule } from '@/features/request-management/request-module'

/**
 * Server state of the dashboard's aggregates (spec 0107 D-5): synchronous,
 * no polling — unlike the CSV report's `ExportRun` cycle. `enabled` mirrors
 * the filter form's own validity (e.g. every branch deselected fails the
 * shared Zod schema, AC-044): an invalid form never issues a request. The
 * "closed panel costs nothing" half of D-5 comes from `RequestDashboardPanel`
 * only ever mounting this hook while open, the same convention
 * `useModuleStats`/`ModuleStatsPanelBody` already use.
 *
 * `query` doubles as the query key (`requestManagementKeys.dashboard`), so a
 * changed filter is a DIFFERENT cache entry — a late response for a filter
 * combination the user has since left behind can never land on the one
 * currently rendered. That is what `applied` (the contract's echo of the
 * request's own filters) is FOR; TanStack's key-scoped cache already
 * delivers it structurally, so no separate client-side comparison is added.
 */
export function useRequestDashboard(query: RequestDashboardQuery, enabled: boolean) {
  const module = useRequestModule()

  return useQuery({
    queryKey: requestManagementKeys.dashboard(module.key, query),
    queryFn: () => fetchRequestManagementDashboard(module.apiBasePath, query),
    enabled,
  })
}

/**
 * Stable callback that marks every dashboard entry of the module stale after
 * a write that moves an indicator. The panel stays mounted while a note is
 * written in the modal work panel or the row's notes dialog, and
 * `refetchOnWindowFocus` is off: without this the tiles keep the value read
 * when the panel opened. Same semantics as `useInvalidateModuleStats` — an
 * open panel refetches now, a closed one is served fresh on the next open.
 */
export function useInvalidateRequestDashboard(): () => void {
  const queryClient = useQueryClient()
  const module = useRequestModule()

  return useCallback(() => {
    void queryClient.invalidateQueries({ queryKey: requestManagementKeys.dashboardAll(module.key) })
  }, [queryClient, module.key])
}
